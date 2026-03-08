// Manline OC4: lightweight SimpleCheckout reload engine (block-based AJAX).
(function() {
  window.__simplecheckoutLiteActive = true;

  var busy = false;
  var pending = false;
  var pendingExtra = null;

  function mergeExtra(base, extra) {
    var result = {};

    if (base && typeof base === 'object') {
      Object.keys(base).forEach(function(key) {
        result[key] = base[key];
      });
    }

    if (extra && typeof extra === 'object') {
      Object.keys(extra).forEach(function(key) {
        result[key] = extra[key];
      });
    }

    return Object.keys(result).length ? result : null;
  }

  function getContainer() {
    return document.getElementById('simplecheckout_form_0');
  }

  function closest(el, selector) {
    return el && el.closest ? el.closest(selector) : null;
  }

  function getReloadUrl(container) {
    if (!container) {
      return '';
    }

    var url = container.getAttribute('data-reload-url');

    if (url) {
      return url;
    }

    return 'index.php?route=checkout/simplecheckout.reload&language=' + encodeURIComponent(window.ocLanguage || '');
  }

  function serializeContainer(container) {
    var payload = new URLSearchParams();
    var fields = container.querySelectorAll('input, select, textarea');

    for (var i = 0; i < fields.length; i++) {
      var field = fields[i];

      if (!field.name || field.disabled) {
        continue;
      }

      if (closest(field, '#simplecheckout_payment_form')) {
        continue;
      }

      if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) {
        continue;
      }

      payload.append(field.name, field.value);
    }

    return payload;
  }

  function replaceBlock(id, html) {
    var current = document.getElementById(id);

    if (!current) {
      return;
    }

    if (!html) {
      if (id === 'simplecheckout_payment_form') {
        current.innerHTML = '';
      }

      return;
    }

    var wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();

    var next = wrapper.firstElementChild;

    if (next && next.id === id) {
      current.replaceWith(next);
    } else {
      current.innerHTML = html;
    }
  }

  function applyBlocks(blocks) {
    if (!blocks) {
      return;
    }

    replaceBlock('simplecheckout_cart', blocks.cart);
    replaceBlock('simplecheckout_customer', blocks.customer);
    replaceBlock('simplecheckout_shipping_address', blocks.shipping_address);
    replaceBlock('simplecheckout_shipping', blocks.shipping);
    replaceBlock('simplecheckout_comment', blocks.comment);
    replaceBlock('simplecheckout_payment', blocks.payment);
    replaceBlock('simplecheckout_payment_form', blocks.payment_form);

    var remove = document.getElementById('simplecheckout_remove');

    if (remove) {
      remove.value = '';
    }

    invokeCompat('cuponPolt');
    invokeCompat('freeDeliveryCart');
    invokeCompat('freeDelivery');
    invokeCompat('stcart');
    invokeCompat('initNovaPoshtaAutocomplete');

    syncHeaderCartFromCheckout();
    refreshHeaderCart();
  }

  function invokeCompat(name) {
    if (typeof window[name] !== 'function') {
      return;
    }

    try {
      window[name]();
    } catch (err) {
      console.error(err);
    }
  }

  function getHeaderCart(container) {
    if (!container) {
      return null;
    }

    return container.querySelector('#header #cart') || container.querySelector('#cart');
  }

  function refreshHeaderCart() {
    var pageCart = getHeaderCart(document);

    if (!pageCart || typeof window.fetch !== 'function') {
      return;
    }

    var language = window.ocLanguage ? '&language=' + encodeURIComponent(window.ocLanguage) : '';
    var url = 'index.php?route=common/cart.info' + language;

    fetch(url, {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    }).then(function(response) {
      return response.text();
    }).then(function(html) {
      if (!html) {
        return;
      }

      var wrapper = document.createElement('div');
      wrapper.innerHTML = html;

      var nextCart = getHeaderCart(wrapper);
      var currentCart = getHeaderCart(document);

      if (nextCart && currentCart) {
        currentCart.replaceWith(nextCart);
      }
    }).catch(function(err) {
      console.error(err);
    });
  }

  function syncHeaderCartFromCheckout() {
    var form = document.getElementById('simplecheckout_form_0');

    if (!form) {
      return;
    }

    var qtyInputs = form.querySelectorAll('input[name^="quantity["]');
    var qty = 0;

    for (var i = 0; i < qtyInputs.length; i++) {
      var value = parseInt(qtyInputs[i].value, 10);

      if (Number.isFinite(value) && value > 0) {
        qty += value;
      }
    }

    var totalNode = form.querySelector('#total_total .simplecheckout-cart-total-value');
    var totalText = totalNode ? totalNode.textContent.trim() : '';

    if (!totalText) {
      var fallbackNode = form.querySelector('#simplecheckout_cart_total');
      totalText = fallbackNode ? fallbackNode.textContent.trim() : '';
    }

    var headerCart = document.querySelector('#header #cart');

    if (!headerCart) {
      return;
    }

    var qtyNode = headerCart.querySelector('.cart_qnt');
    var totalNodeHeader = headerCart.querySelector('.cart_total');

    if (qtyNode) {
      qtyNode.textContent = String(qty);
    }

    if (totalNodeHeader && totalText) {
      totalNodeHeader.textContent = totalText;
    }
  }

  function reloadAll(extra) {
    var container = getContainer();

    if (!container) {
      return Promise.resolve();
    }

    if (busy) {
      pending = true;
      pendingExtra = mergeExtra(pendingExtra, extra);
      return Promise.resolve();
    }

    var url = getReloadUrl(container);

    if (!url) {
      return Promise.resolve();
    }

    busy = true;

    var payload = serializeContainer(container);

    if (extra) {
      Object.keys(extra).forEach(function(key) {
        payload.set(key, String(extra[key]));
      });
    }

    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: payload.toString()
    }).then(function(response) {
      return response.json();
    }).then(function(json) {
      if (json.redirect) {
        var redirect = String(json.redirect || '');

        if (redirect.indexOf('route=checkout/cart') !== -1 || /\/cart(?:[/?]|$)/.test(redirect)) {
          return;
        }

        window.location.href = redirect;
        return;
      }

      applyBlocks(json.blocks);
    }).catch(function(err) {
      // Keep console output for checkout debugging in migration stage.
      console.error(err);
    }).finally(function() {
      busy = false;

      if (pending) {
        var nextExtra = pendingExtra;
        pending = false;
        pendingExtra = null;
        reloadAll(nextExtra);
      }
    });
  }

  function getQuantityInputByKey(key) {
    var inputs = document.querySelectorAll('#simplecheckout_form_0 input[name^="quantity["]');

    for (var i = 0; i < inputs.length; i++) {
      if (inputs[i].getAttribute('data-product-key') === key) {
        return inputs[i];
      }
    }

    return null;
  }

  function changeQuantity(key, delta) {
    var input = getQuantityInputByKey(key);

    if (!input) {
      return;
    }

    var current = parseInt(input.value, 10);

    if (!Number.isFinite(current) || current < 1) {
      current = 1;
    }

    var next = current + delta;

    if (next < 1) {
      next = 1;
    }

    input.value = String(next);
  }

  function runAction(action, key) {
    if (action === 'increaseProductQuantity' && key) {
      changeQuantity(key, 1);
      reloadAll();
      return true;
    }

    if (action === 'decreaseProductQuantity' && key) {
      changeQuantity(key, -1);
      reloadAll();
      return true;
    }

    if (action === 'removeProduct' && key) {
      var remove = document.getElementById('simplecheckout_remove');

      if (remove) {
        remove.value = key;
      }

      reloadAll();
      return true;
    }

    if (action === 'changeProductQuantity' || action === 'reloadAll') {
      reloadAll();
      return true;
    }

    if (action === 'createOrder') {
      reloadAll({ create_order: 1 });
      return true;
    }

    return false;
  }

  function onClick(event) {
    var button = closest(event.target, '[data-onclick]');

    if (!button) {
      return;
    }

    if (!closest(button, '#simplecheckout_form_0')) {
      return;
    }

    var action = button.getAttribute('data-onclick') || '';
    var key = button.getAttribute('data-product-key') || '';

    if (!action) {
      return;
    }

    if (runAction(action, key)) {
      event.preventDefault();
      return;
    }
  }

  function onChange(event) {
    var field = event.target;

    if (!closest(field, '#simplecheckout_form_0')) {
      return;
    }

    var action = field.getAttribute('data-onchange');

    if (!action) {
      return;
    }

    if (action === 'changeProductQuantity' || action === 'reloadAll') {
      reloadAll();
    }
  }

  document.addEventListener('click', onClick);
  document.addEventListener('change', onChange);

  // Compatibility hooks for legacy scripts.
  window.reloadAll = function(extra) {
    return reloadAll(extra);
  };

  window.increaseProductQuantity = function(key) {
    return runAction('increaseProductQuantity', String(key || ''));
  };

  window.decreaseProductQuantity = function(key) {
    return runAction('decreaseProductQuantity', String(key || ''));
  };

  window.removeProduct = function(key) {
    return runAction('removeProduct', String(key || ''));
  };

  window.changeProductQuantity = function() {
    return runAction('changeProductQuantity', '');
  };

  window.load_simplecheckout = function() {
    reloadAll();
  };
})();
