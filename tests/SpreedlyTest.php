<?php

include_once(dirname(__FILE__)."/setup.inc");

class SpreedlyTest extends PHPUnit_Framework_TestCase {
	public function testXmlParams() {
		$obj = new StdClass();
		$obj->first = "one";
		$obj->second = new StdClass();
		$obj->second->one = 123;
		$obj->second->two = 234;
		$obj->third = "three";
		$xml = Spreedly::__to_xml_params($obj);
		$this->assertRegExp("/<first>one<\/first>\s*<second><one>123<\/one><two>234<\/two><\/second>\s*<third>three<\/third>/", $xml);

		// test funky encoding
		$obj = new StdClass();
		$obj->container = new StdClass();
		$obj->container->user1 = "Able & Baker";
		$obj->container->user2 = "Able < Baker >";
		$obj->container->user3 = "Able/Baker&amp;Charlie";
		$xml = Spreedly::__to_xml_params($obj);
		$this->assertRegExp("/<container><user1>Able &amp; Baker<\/user1><user2>Able &lt; Baker &gt;<\/user2><user3>Able\/Baker&amp;amp;Charlie<\/user3><\/container>/", $xml);
	}

	public function testWipe() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);

		SpreedlySubscriber::wipe();
		$sub = SpreedlySubscriber::create(1, "abel@nospam.com", "abel");
		$this->assertTrue($sub instanceof SpreedlySubscriber);

		SpreedlySubscriber::wipe();
		$sub = SpreedlySubscriber::find(1);
		$this->assertNull($sub);
	}

	public function testAdminUrl() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);
		$url = Spreedly::get_admin_subscriber_url(123);
		$this->assertEquals($url, "https://subs.pinpayments.com/{$test_site_name}/subscribers/123");
	}

	public function testConfigure() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);
		$this->assertNotNull(Spreedly::$token);
		$this->assertEquals(Spreedly::$token, $test_token);
		$this->assertEquals(Spreedly::$site_name, $test_site_name);
		$this->assertEquals(Spreedly::$base_uri, "https://subs.pinpayments.com/api/v4/{$test_site_name}");
	}

	public function testCreate() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);

		SpreedlySubscriber::wipe();
		$sub = SpreedlySubscriber::find(1);
		$this->assertNull($sub);

		$sub = SpreedlySubscriber::create(1, "abel@nospam.com", "abel");
		$sub = SpreedlySubscriber::find(1);
		$this->assertTrue($sub instanceof SpreedlySubscriber);
		$this->assertFalse($sub->active);
	}

	public function testDelete() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);

		SpreedlySubscriber::wipe();
		$sub = SpreedlySubscriber::find(1);
		$this->assertNull($sub);

		$sub = SpreedlySubscriber::create(1, "abel@nospam.com", "abel");
		$sub->comp(1, "days", "basic");
		$sub = SpreedlySubscriber::find(1);
		$this->assertTrue($sub instanceof SpreedlySubscriber);

		SpreedlySubscriber::delete(1);
		$sub = SpreedlySubscriber::find(1);
		$this->assertNull($sub);
	}

	public function testEditSubscriberUrl() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);
		$url = Spreedly::get_edit_subscriber_url("XYZ");
		$this->assertEquals("https://subs.pinpayments.com/{$test_site_name}/subscriber_accounts/XYZ", $url);
	}

	public function testFind() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);

		SpreedlySubscriber::wipe();
		$sub = SpreedlySubscriber::find(1);
		$this->assertNull($sub);

		$sub = SpreedlySubscriber::create(1, "abel@nospam.com", "abel");
		$sub = SpreedlySubscriber::create(2, "baker@nospam.com", "baker");
		$sub = SpreedlySubscriber::find(1);
		$this->assertTrue($sub instanceof SpreedlySubscriber);

		$sub = SpreedlySubscriber::find(2);
		$this->assertTrue($sub instanceof SpreedlySubscriber);
	}

	public function testFreeTrial() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);

		SpreedlySubscriber::wipe();
		$sub = SpreedlySubscriber::find(1);
		$this->assertNull($sub);

		$sub1 = SpreedlySubscriber::create(1, "abel@nospam.com", "abel");
		$sub1->comp(1, "days", "full");
		$sub2 = SpreedlySubscriber::create(2, "baker@nospam.com", "baker");

		$trial_plan = SpreedlySubscriptionPlan::find_by_name("Free Trial");
		$this->assertNotNull($trial_plan);
		try {
			$sub1->activate_free_trial($trial_plan->id);
			$this->fail("activated trial for existing customer");
		} catch (SpreedlyException $e) {
			// good
		}

		$sub2->activate_free_trial($trial_plan->id);
		$sub2 = SpreedlySubscriber::find($sub2->get_id());
		$this->assertNotNull($sub2);
		$this->assertTrue($sub2->on_trial);
	}

	public function testGetAll() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);

		SpreedlySubscriber::wipe();
		$sub = SpreedlySubscriber::find(1);
		$this->assertNull($sub);

		$sub = SpreedlySubscriber::create(1, "abel@nospam.com", "abel");
		$sub = SpreedlySubscriber::create(2, "baker@nospam.com", "baker");
		$sub = SpreedlySubscriber::create(3, "charlie@nospam.com", "charlie");
		$subs = SpreedlySubscriber::get_all();
		$this->assertEquals(3, count($subs));
	}

	public function testPlans() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);

		$plans = SpreedlySubscriptionPlan::get_all();
		$this->assertEquals(4, count($plans));
		$this->assertEquals("full", $plans[0]->feature_level);
		$this->assertEquals("basic", $plans[2]->feature_level);

		$full = SpreedlySubscriptionPlan::find_by_name("Annual");
		$this->assertNotNull($full);

		$id = $full->id;
		$full = SpreedlySubscriptionPlan::find($id);
		$this->assertNotNull($full);
	}

	public function testStopAutoRenew() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);

		SpreedlySubscriber::wipe();
		$sub = SpreedlySubscriber::create(75, null, "charlie");
		$sub->comp(32, "days", "basic");
		$sub = SpreedlySubscriber::find(75);
		$this->assertTrue($sub instanceof SpreedlySubscriber);
		$this->assertFalse($sub->recurring);

		$sub->stop_auto_renew();
		$sub = SpreedlySubscriber::find(75);
		$this->assertFalse($sub->recurring);
	}

	public function testComp() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);
		SpreedlySubscriber::wipe();

		// test comping when user has no subscription
		$sub = SpreedlySubscriber::create(75, null, "charlie");
		try {
			$sub->comp(32, "days");
			$this->fail("Exception should be thrown, no feature level given");
		} catch (SpreedlyException $e) {
			// good
		}
		$sub->comp(32, "days", "basic");
		$sub = SpreedlySubscriber::find(75);
		$this->assertTrue($sub->active);
		$this->assertFalse($sub->on_trial);

		// test comping when user has a paid subscription already
		$sub = SpreedlySubscriber::create(76, null, "baker");
		$annual = SpreedlySubscriptionPlan::find_by_name("Annual");
		$invoice = SpreedlyInvoice::create($sub->get_id(), $annual->id, $sub->screen_name, "test@test.com");
		$response = $invoice->pay("4222222222222", "visa", "123", "12", date("Y")+1, "Test", "User");
		$sub = SpreedlySubscriber::find(76);
		$prev_active_until = $sub->active_until;
		$sub = $sub->comp(30, "days");
		$this->assertEquals(30, ($sub->active_until - $prev_active_until)/(60*60*24));

		// make sure this still works, even though the 3rd param should be ignored
		$sub = $sub->comp(1, "days", "full");
		$this->assertEquals(31, ($sub->active_until - $prev_active_until)/(60*60*24));
	}

	public function testSubscriberUrl() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);

		$url = Spreedly::get_subscribe_url(123, 10);
		$this->assertEquals("https://subs.pinpayments.com/{$test_site_name}/subscribers/123/subscribe/10", $url);

		$url = Spreedly::get_subscribe_url(123, 10, "test_user");
		$this->assertEquals("https://subs.pinpayments.com/{$test_site_name}/subscribers/123/subscribe/10/test_user", $url);

		$url = Spreedly::get_subscribe_url(123, 10, "test/ user");
		$this->assertEquals("https://subs.pinpayments.com/{$test_site_name}/subscribers/123/subscribe/10/test%2F+user", $url);

		$url = Spreedly::get_subscribe_url(123, 10, array(
				"return_url"=>"http://www.google.com",
				"email"=>"test@nospam.com",
				"token"=>"XYZ"
			));
		$this->assertEquals("https://subs.pinpayments.com/{$test_site_name}/subscribers/123/XYZ/subscribe/10?return_url=http%3A%2F%2Fwww.google.com&email=test%40nospam.com", $url);

		$url = Spreedly::get_subscribe_url(123, 10, array(
				"screen_name"=>"joe",
				"email"=>"test@nospam.com",
			));
		$this->assertEquals("https://subs.pinpayments.com/{$test_site_name}/subscribers/123/subscribe/10/joe?email=test%40nospam.com", $url);

		$url = Spreedly::get_subscribe_url(123, 10, array(
				"screen_name"=>"joe"
			));
		$this->assertEquals("https://subs.pinpayments.com/{$test_site_name}/subscribers/123/subscribe/10/joe", $url);

		try {
			$url = Spreedly::get_subscribe_url(123, 10, array(
					"email"=>"test@nospam.com",
					"xyz"=>"http://www.google.com",
					"token"=>"XYZ"
				));
			$this->fail("expected an exception because xyz isn't valid");
		} catch (Exception $e) {
			// good
		}
	}

	public function testUpdate() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);

		SpreedlySubscriber::wipe();
		$sub = SpreedlySubscriber::create(75, null, "charlie");
		$this->assertEquals(75, $sub->get_id());

		$sub->update("test@test.com", "able");
		$sub = SpreedlySubscriber::find(75);
		$this->assertNotNull($sub);
		$this->assertEquals("test@test.com", $sub->email);
		$this->assertEquals("able", $sub->screen_name);

		$sub->update(null, null, 100);
		$sub = SpreedlySubscriber::find(75);
		$this->assertNull($sub);
		$sub = SpreedlySubscriber::find(100);
		$this->assertNotNull($sub);

		SpreedlySubscriber::create(75, null, "baker");
		try {
			$sub->update(null, null, 75);
			$this->fail("expected an exception to be thrown");
		} catch (SpreedlyException $e) {
		}
	}

	public function testLifetimeComp() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);
		SpreedlySubscriber::wipe();

		$sub = SpreedlySubscriber::create(75, null, "charlie");
		$this->assertEquals(75, $sub->get_id());
		$this->assertFalse($sub->lifetime_subscription);
		$sub = $sub->lifetime_comp("full");
		$this->assertTrue($sub->lifetime_subscription);
	}

	public function testAddStoreCredit() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);
		SpreedlySubscriber::wipe();

		$sub = SpreedlySubscriber::create(75, null, "charlie");
		$this->assertEquals(75, $sub->get_id());
		$this->assertEquals(0, $sub->store_credit);
		$sub->add_store_credit(2.50);
		$sub = SpreedlySubscriber::find(75);
		$this->assertEquals(2.50, $sub->store_credit);
	}

	public function testAllowFreeTrial() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);
		SpreedlySubscriber::wipe();

		$trial_plan = SpreedlySubscriptionPlan::find_by_name("Free Trial");
		$sub = SpreedlySubscriber::create(75, null, "charlie");
		$sub->activate_free_trial($trial_plan->id);
		$sub = SpreedlySubscriber::find(75);
		$this->assertFalse($sub->eligible_for_free_trial);
		$sub->allow_free_trial();
		$sub = SpreedlySubscriber::find(75);
		$this->assertTrue($sub->eligible_for_free_trial);
	}

	public function testCreateInvoice() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);
		SpreedlySubscriber::wipe();

		// create invoice for existing customer
		$trial_plan = SpreedlySubscriptionPlan::find_by_name("Free Trial");
		$sub = SpreedlySubscriber::create(75, null, "charlie");
		$sub->activate_free_trial($trial_plan->id);
		$sub = SpreedlySubscriber::find(75);

		$annual = SpreedlySubscriptionPlan::find_by_name("Annual");
		$invoice = SpreedlyInvoice::create($sub->get_id(), $annual->id, $sub->screen_name, "test@test.com");

		$this->assertTrue($invoice->subscriber instanceof SpreedlySubscriber);
		$this->assertEquals("charlie", $invoice->subscriber->screen_name);
		$this->assertEquals(25, $invoice->line_items[0]->amount);

		// create invoice for new customer
		$monthly = SpreedlySubscriptionPlan::find_by_name("Monthly");
		$invoice = SpreedlyInvoice::create(10, $monthly->id, "able", "able@test.com");
		$this->assertTrue($invoice->subscriber instanceof SpreedlySubscriber);
		$this->assertEquals("able", $invoice->subscriber->screen_name);
		$this->assertEquals(2.50, $invoice->line_items[0]->amount);
		$this->assertTrue(SpreedlySubscriber::find(10) instanceof SpreedlySubscriber);
	}

	public function testPayInvoice() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);
		SpreedlySubscriber::wipe();

		// create invoice for existing customer
		$trial_plan = SpreedlySubscriptionPlan::find_by_name("Free Trial");
		$sub = SpreedlySubscriber::create(75, null, "charlie");
		$sub->activate_free_trial($trial_plan->id);
		$sub = SpreedlySubscriber::find(75);

		$annual = SpreedlySubscriptionPlan::find_by_name("Annual");
		$invoice = SpreedlyInvoice::create($sub->get_id(), $annual->id, $sub->screen_name, "test@test.com");
		$response = $invoice->pay("4222222222222", "visa", "123", "13", date("Y")+1, "Test", "User");
		$this->assertTrue($response instanceof SpreedlyErrorList);

		$response = $invoice->pay("4222222222222", "visa", "123", "12", date("Y")-1, "Test", "User");
		$this->assertTrue($response instanceof SpreedlyErrorList);

		// declined
		try {
			$response = $invoice->pay("4012888888881881", "visa", "123", "12", date("Y")+1, "Test", "User");
			$this->fail("An exception should have been thrown");
		} catch (SpreedlyException $e) {
			$this->assertEquals(403, $e->getCode());
		}

		$response = $invoice->pay("4222222222222", "visa", "123", "12", date("Y")+1, "Test", "User");
		$this->assertTrue($response->closed);

		// test paying paid invoice
		try {
			$response = $invoice->pay("4222222222222", "visa", "123", "12", date("Y")+1, "Test", "User");
			$this->fail("An exception should have been thrown");
		} catch (SpreedlyException $e) {
			$this->assertEquals(403, $e->getCode());
		}

		// test adding fees
		$this->assertEquals(75, $sub->get_id());
		$sub->add_fee("Daily Bandwidth Charge", "313 MB used", "Traffic Fees", 2.34);
	}

	public function testGetTransactions() {
		global $test_site_name, $test_token;
		Spreedly::configure($test_site_name, $test_token);
		SpreedlySubscriber::wipe();

		// create invoice for existing customer
		$trial_plan = SpreedlySubscriptionPlan::find_by_name("Free Trial");
		$sub1 = SpreedlySubscriber::create(75, null, "able");
		$sub1->lifetime_comp("full");
		$sub2 = SpreedlySubscriber::create(76, null, "baker");
		$sub2->activate_free_trial($trial_plan->id);
		$sub3 = SpreedlySubscriber::create(77, null, "charlie");
		$sub3->activate_free_trial($trial_plan->id);

		$transactions = Spreedly::get_transactions();
		$this->assertEquals(3, count($transactions));
		$this->assertEquals("free_trial", $transactions[0]->detail->payment_method);

		// test getting subset of transactions
		$transactions = Spreedly::get_transactions($transactions[1]->id);
		$this->assertEquals(1, count($transactions));
		$this->assertEquals(77, $transactions[0]->subscriber_customer_id);
	}
}
