<?php
namespace Opencart\Catalog\Controller\Extension\Manline\Module;

class Slider extends \Opencart\System\Engine\Controller {
	public function index(array $setting): string {
		// Banner
		$this->load->model('design/banner');
		$this->load->model('tool/image');

		$banner_id = (int)($setting['banner_id'] ?? 0);
		$width = (int)($setting['width'] ?? 1170);
		$height = (int)($setting['height'] ?? 380);

		$data['slides'] = [];

		if ($banner_id) {
			$results = $this->model_design_banner->getBanner($banner_id);

			foreach ($results as $result) {
				$image = '';
				if (!empty($result['image']) && is_file(DIR_IMAGE . html_entity_decode((string)$result['image'], ENT_QUOTES, 'UTF-8'))) {
					$image = $this->model_tool_image->resize(html_entity_decode((string)$result['image'], ENT_QUOTES, 'UTF-8'), $width, $height);
				}

				if ($image) {
					$data['slides'][] = [
						'title' => $result['title'] ?? '',
						'href'  => $result['link'] ?? '',
						'image' => $image
					];
				}
			}
		}

		if (!$data['slides']) {
			return '';
		}

		$data['dots'] = !empty($setting['dots']);
		$data['autoplay'] = !empty($setting['autoplay']);
		$data['autoplay_speed'] = (int)($setting['autoplay_speed'] ?? 4000);

		return $this->load->view('extension/manline/module/slider', $data);
	}
}
