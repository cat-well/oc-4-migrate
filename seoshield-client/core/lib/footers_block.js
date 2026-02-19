if(typeof jQuery=="undefined"){
	var headTag=document.getElementsByTagName("head")[0];
	var jqTag=document.createElement('script');
	jqTag.type="text/javascript";
	jqTag.src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js";
	jqTag.onload=function(){
		$.noConflict();
		jQuery(document).ready(function($){
			if ($('.container.shield__footers_module_block__wrapper').length != 0 && typeof window.footers_block_move_to_js != 'undefined' && window.footers_block_move_to_js != ""){
				$('#' + window.footers_block_move_to_js).replaceWith($('.container.shield__footers_module_block__wrapper'));
			}
			$('[switch-block]').unbind('click');
			$('body')
			.on('click', '.shield__footers_module_block__wrapper__card-header[data-target]', function(){
				$('' + $(this).data('target')).toggleClass('show');
			})
			.on('click', '[switch-block]', function(){
				$('[switch-block].shield__footers_module_block__wrapper__btn-success').removeClass('shield__footers_module_block__wrapper__btn-success').addClass('shield__footers_module_block__wrapper__btn-light');
				$(this).removeClass('shield__footers_module_block__wrapper__btn-light').addClass('shield__footers_module_block__wrapper__btn-success');
				if ($(this).attr('target') == '#top-categories'){
					if ($(this).data('custom-switch')){
						$('[block-target]#top-categories span:not([city-info])').hide();
						$('[city-info]').show();
					} else {
						$('[city-info]').hide();
						$('[block-target]#top-categories span:not([city-info])').show();
					}
				}
				$('[block-target]').hide();
				$('[block-target]'+$(this).attr('target')).show();
			});
		});
	};
	headTag.appendChild(jqTag);
} else {
	jQuery(document).ready(function($){
		if ($('.container.shield__footers_module_block__wrapper').length != 0 && typeof window.footers_block_move_to_js != 'undefined' && window.footers_block_move_to_js != ""){
			$('#' + window.footers_block_move_to_js).replaceWith($('.container.shield__footers_module_block__wrapper'));
		}
		$('[switch-block]').unbind('click');
		$('body')
		.on('click', '.shield__footers_module_block__wrapper__card-header[data-target]', function(){
			$('' + $(this).data('target')).toggleClass('show');
		})
		.on('click', '[switch-block]', function(){
			$('[switch-block].shield__footers_module_block__wrapper__btn-success').removeClass('shield__footers_module_block__wrapper__btn-success').addClass('shield__footers_module_block__wrapper__btn-light');
			$(this).removeClass('shield__footers_module_block__wrapper__btn-light').addClass('shield__footers_module_block__wrapper__btn-success');
			if ($(this).attr('target') == '#top-categories'){
				if ($(this).data('custom-switch')){
					$('[block-target]#top-categories span:not([city-info])').hide();
					$('[city-info]').show();
				} else {
					$('[city-info]').hide();
					$('[block-target]#top-categories span:not([city-info])').show();
				}
			}
			$('[block-target]').hide();
			$('[block-target]'+$(this).attr('target')).show();
		});
	});
}