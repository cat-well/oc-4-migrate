// Manline: minimal SimpleCheckout interactions for OC4 scaffold.
// This is NOT the full SimpleCheckout engine; it just wires +/- and remove to a page reload.
(function(){
  function closest(el, sel){ return el && el.closest ? el.closest(sel) : null; }

  function onClick(e){
    var t = e.target;
    var btn = closest(t, '[data-onclick]');
    if(!btn) return;

    var action = btn.getAttribute('data-onclick');
    var key = btn.getAttribute('data-product-key');

    if(action === 'removeProduct' && key){
      e.preventDefault();
      // Temporary: redirect to cart remove (works on OC4)
      var url = 'index.php?route=checkout/cart&language=' + encodeURIComponent(window.ocLanguage || '') + '&remove=' + encodeURIComponent(key);
      window.location.href = url;
    }
  }

  document.addEventListener('click', onClick);
})();
