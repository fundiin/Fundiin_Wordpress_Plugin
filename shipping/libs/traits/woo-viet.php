<?php

namespace BluecoralWoo\Traits;

Trait WooViet {
	
	public function getDataFormattedWooViet($raw_metas = []) {
		$data = [];
		// Parse metas
		$metas = [];
		$meta_keys = [
			'_billing_address_1' => self::FIELD_BILLING_ADDRESS,
			'_billing_city' => self::FIELD_BILLING_CITY,
			'_billing_state' => self::FIELD_BILLING_STATE,
			'_billing_country' => self::FIELD_BILLING_COUNTRY,
			'_shipping_address_1' => self::FIELD_SHIPPING_ADDRESS,
			'_shipping_city' => self::FIELD_SHIPPING_CITY,
			'_shipping_state' => self::FIELD_SHIPPING_STATE,
			'_shipping_country' => self::FIELD_SHIPPING_COUNTRY,
		];
		foreach ($meta_keys as $raw_meta_key => $meta_key) {
			if (!empty($raw_metas[$raw_meta_key])) {
				$metas[$meta_key] = $raw_metas[$raw_meta_key][0] ? $raw_metas[$raw_meta_key][0] : $raw_metas[$raw_meta_key];
			}
		}
		// no ward field
		$metas[self::FIELD_BILLING_WARD] = '';
		if (!empty($metas[self::FIELD_SHIPPING_ADDRESS])) {
			$metas[self::FIELD_SHIPPING_WARD] = '';
		}
		$data['metas'] = $metas;
		// Formatted data
		$formatted = [];
		foreach ($metas as $key => $value) {
			switch ($key) {
				case self::FIELD_BILLING_STATE:
				case self::FIELD_SHIPPING_STATE:
					$formatted[$key] = $this->getProvinceWooViet($value);
					break;
				
				default:
					$formatted[$key] = $value;					
			}
		}
		$data['formatted'] = $formatted;		
		return $data;
	}
	
	public function getProvinceWooViet($raw_province = '') {
		$provinces = $this->getProvincesWooViet();
		return $provinces[$raw_province] ? $provinces[$raw_province] : '';
	}
	
	public function getProvincesWooViet() {
		return [
			'HO-CHI-MINH' => 'Hồ Chí Minh',
			'HA-NOI' => 'Hà Nội',
			'AN-GIANG' => 'An Giang',
			'BAC-GIANG' => 'Bắc Giang',
			'BAC-KAN' => 'Bắc Kạn',
			'BAC-LIEU' => 'Bạc Liêu',
			'BAC-NINH' => 'Bắc Ninh',
			'BA-RIA-VUNG-TAU' => 'Bà Rịa - Vũng Tàu',
			'BEN-TRE' => 'Bến Tre',
			'BINH-DINH' => 'Bình Định',
			'BINH-DUONG' => 'Bình Dương',
			'BINH-PHUOC' => 'Bình Phước',
			'BINH-THUAN' => 'Bình Thuận',
			'CA-MAU' => 'Cà Mau',
			'CAN-THO' => 'Cần Thơ',
			'CAO-BANG' => 'Cao Bằng',
			'DAK-LAK' => 'Đắk Lắk',
			'DAK-NONG' => 'Đắk Nông',
			'DA-NANG' => 'Đà Nẵng',
			'DIEN-BIEN' => 'Điện Biên',
			'DONG-NAI' => 'Đồng Nai',
			'DONG-THAP' => 'Đồng Tháp',
			'GIA-LAI' => 'Gia Lai',
			'HA-GIANG' => 'Hà Giang',
			'HAI-DUONG' => 'Hải Dương',
			'HAI-PHONG' => 'Hải Phòng',
			'HA-NAM' => 'Hà Nam',
			'HA-TINH' => 'Hà Tĩnh',
			'HAU-GIANG' => 'Hậu Giang',
			'HOA-BINH' => 'Hoà Bình',
			'HUNG-YEN' => 'Hưng Yên',
			'KHANH-HOA' => 'Khánh Hòa',
			'KIEN-GIANG' => 'Kiên Giang',
			'KONTUM' => 'Kon Tum',
			'LAI-CHAU' => 'Lai Châu',
			'LAM-DONG' => 'Lâm Đồng',
			'LANG-SON' => 'Lạng Sơn',
			'LAO-CAI' => 'Lào Cai',
			'LONG-AN' => 'Long An',
			'NAM-DINH' => 'Nam Định',
			'NGHE-AN' => 'Nghệ An',
			'NINH-BINH' => 'Ninh Bình',
			'NINH-THUAN' => 'Ninh Thuận',
			'PHU-THO' => 'Phú Thọ',
			'PHU-YEN' => 'Phú Yên',
			'QUANG-BINH' => 'Quảng Bình',
			'QUANG-NAM' => 'Quảng Nam',
			'QUANG-NGAI' => 'Quảng Ngãi',
			'QUANG-NINH' => 'Quảng Ninh',
			'QUANG-TRI' => 'Quảng Trị',
			'SOC-TRANG' => 'Sóc Trăng',
			'SON-LA' => 'Sơn La',
			'TAY-NINH' => 'Tây Ninh',
			'THAI-BINH' => 'Thái Bình',
			'THAI-NGUYEN' => 'Thái Nguyên',
			'THANH-HOA' => 'Thanh Hóa',
			'THUA-THIEN-HUE' => 'Thừa Thiên Huế',
			'TIEN-GIANG' => 'Tiền Giang',
			'TRA-VINH' => 'Trà Vinh',
			'TUYEN-QUANG' => 'Tuyên Quang',
			'VINH-LONG' => 'Vĩnh Long',
			'VINH-PHUC' => 'Vĩnh Phúc',
			'YEN-BAI' => 'Yên Bái',
		];
	}
	
}
