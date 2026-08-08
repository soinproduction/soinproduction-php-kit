<?php
	add_action('init', function () {
		if (!is_admin()) {
			return;
		}

		$page = $_GET['page'] ?? '';
		if (!str_contains($page, 'duplicator-pro') && !str_contains($page, 'dup')) {
			return;
		}

		if (class_exists('Duplicator\Addons\ProBase\Models\LicenseData')) {
			$licenseData = \Duplicator\Addons\ProBase\Models\LicenseData::getInstance();

			if ($licenseData->getStatus() !== \Duplicator\Addons\ProBase\Models\LicenseData::STATUS_VALID || $licenseData->getLicenseType() !== 11 || empty($licenseData->getKey())) {
				$refObject = new \ReflectionObject($licenseData);
				$propKey = $refObject->getProperty('licenseKey');
				$propKey->setAccessible(true);
				$propKey->setValue($licenseData, 'd8a57e3f890b24e6ca81d9f67a213e4b');

				$propStatus = $refObject->getProperty('status');
				$propStatus->setAccessible(true);
				$propStatus->setValue($licenseData, 0);

				$propType = $refObject->getProperty('type');
				$propType->setAccessible(true);
				$propType->setValue($licenseData, 11);

				$propData = $refObject->getProperty('data');
				$propData->setAccessible(true);
				$propData->setValue($licenseData, [
					'success'            => true,
					'license'            => 'valid',
					'item_id'            => 31,
					'item_name'          => '',
					'checksum'           => '',
					'expires'            => 'lifetime',
					'payment_id'         => -1,
					'customer_name'      => '',
					'customer_email'     => '',
					'license_limit'      => 100,
					'site_count'         => 1,
					'activations_left'   => 99,
					'price_id'           => 11,
					'activeSubscription' => false,
				]);

				$propUpdate = $refObject->getProperty('lastRemoteUpdate');
				$propUpdate->setAccessible(true);
				$propUpdate->setValue($licenseData, gmdate('Y-m-d H:i:s'));
				$licenseData->save();
			}
		}
	}, 5);


//	add_filter('pre_option_rank_math_connect_data', function ($value) {
//		if (class_exists('RankMath\Data_Encryption')) {
//			return [
//				'connected' => true,
//				'plan'      => \RankMath\Data_Encryption::encrypt('agency'),
//				'username'  => \RankMath\Data_Encryption::encrypt('administrator'),
//				'email'     => \RankMath\Data_Encryption::encrypt('admin@yourdomain.local'),
//				'api_key'   => \RankMath\Data_Encryption::encrypt('activated_by_hand_key'),
//			];
//		}
//		return $value;
//	});

//	add_filter('pre_option_wp_mail_smtp_license', function($value) {
//		return [
//			'key'         => 'any-license-key-here',
//			'type'        => 'elite', // Тариф: 'pro', 'agency', 'elite'
//			'is_expired'  => false,
//			'is_disabled' => false,
//			'is_invalid'  => false,
//		];
//	});
//
