<?php
//error_reporting(E_ALL);
class Donate extends MX_Controller {
	private $fields = array();
	function __construct()
	{
		// Call the constructor of MX_Controller
		parent::__construct();
		// Make sure that we are logged in
		$this->user->userArea();

		$this->load->config('donate');
	}

	public function index()
	{
		requirePermission("view");

		$this->template->setTitle(lang("donate_title", "donate"));

		$donate_wikipal = $this->config->item('donate_wikipal');
		$donate_paygol = $this->config->item('donate_paygol');

		$user_id = $this->user->getId();

		$data = array(
						"donate_wikipal" => $donate_wikipal,
						"donate_paygol" => $donate_paygol,
						"user_id" => $user_id,
						"server_name" => $this->config->item('server_name'),
						"currency" => $this->config->item('donation_currency'),
						"currency_sign" => $this->config->item('donation_currency_sign'),
						"multiplier" => $this->config->item('donation_multiplier'),
						"multiplier_paygol" => $this->config->item('donation_multiplier_paygol'),
						"url" => pageURL
						);

		$output = $this->template->loadPage("donate.tpl", $data);

		$this->template->box("<span style='cursor:pointer;' onClick='window.location=\"" . $this->template->page_url . "ucp\"'>" . lang("ucp") . "</span> &rarr; " . lang("donate_panel", "donate"), $output, true, "modules/donate/css/donate.css", "modules/donate/js/donate.js");
	}

	public function success()
	{
		$this->user->getUserData();

		$page = $this->template->loadPage("success.tpl", array('url' => $this->template->page_url));

		$this->template->box(lang("donate_thanks", "donate"), $page, true);
	}

	public function wikipal()
	{
		
		$this->session->unset_userdata('Amount');
		$donate_wikipal 		= $this->config->item('donate_wikipal');
		$Amount 				= $this->input->post("amount");
		$Description 			= $this->input->post("item_name");

		$this->session->set_userdata('Amount', $Amount);
		
		$MerchantID 			= $donate_wikipal["MerchantID"];
		$Price 					= $Amount;
		$Description 			= $Description;
		$InvoiceNumber 			= time();
		$CallbackURL 			= "http://". $_SERVER['HTTP_HOST'] ."/donate/wikipalreturnback";
			
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, 'http://gatepay.co/webservice/paymentRequest.php');
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json'));
		curl_setopt($curl, CURLOPT_POSTFIELDS, "MerchantID=$MerchantID&Price=$Price&Description=$Description&InvoiceNumber=$InvoiceNumber&CallbackURL=". urlencode($CallbackURL));
		curl_setopt($curl, CURLOPT_TIMEOUT, 400);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$result = json_decode(curl_exec($curl));
		curl_close($curl);
		if ($result->Status == 100){
			header('Location: http://gatepay.co/webservice/startPayment.php?au='. $result->Authority);
		} else {
			echo "Error :". $result->Status;
		}
	}
	public function wikipalreturnback()
	{
		$donate_wikipal 	= $this->config->item('donate_wikipal');
		$Amount 			= $this->session->userdata('Amount');
		$Authority 			= $this->input->get("Authority");
		$status 			= $this->input->get("Status");
		
		$this->session->unset_userdata('Amount');

		$MerchantID 		= $donate_wikipal["MerchantID"];
		$Price 				= $Amount;
		$Authority 			= $_POST['authority'];
		$InvoiceNumber 		= $_POST['InvoiceNumber'];

		if ($_POST['status'] == 1) {
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, 'http://gatepay.co/webservice/paymentVerify.php');
			curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/json'));
			curl_setopt($curl, CURLOPT_POSTFIELDS, "MerchantID=$MerchantID&Price=$Price&Authority=$Authority");
			curl_setopt($curl, CURLOPT_TIMEOUT, 400);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$result = json_decode(curl_exec($curl));
			curl_close($curl);
			
			if ($result->Status == 100) {
					$this->fields['message_id'] 		= $result->RefCode;
					$this->fields['custom'] 			= $user_id = $this->user->getId();
					$this->fields['points'] 			= $this->getDpAmount($Amount);
					$this->fields['timestamp'] 			= time();
					$this->fields['converted_price'] 	= $Amount;
					$this->fields['currency'] 			= $this->config->item('donation_currency_sign');
					$this->fields['price'] 				= $Amount;
					$this->fields['country'] 			= 'IR';
					$this->db->query("UPDATE `account_data` SET `dp` = `dp` + ? WHERE `id` = ?", array($this->fields['points'], $this->fields['custom']));
					$this->updateMonthlyIncome($Amount);
					$this->db->insert("paygol_logs", $this->fields);
					redirect($this->template->page_url."ucp");
					exit;
					
			} else {
				echo "Error ". $result->Status;
			}
		} else {
			echo 'Transaction Canceled By User';
		}
	}
	
	private function getDpAmount($Amount)
	{
		$config = $this->config->item('donate_wikipal');

		$points = $config['values'];
		return $points[$Amount];
	}

	private function updateMonthlyIncome($price)
	{
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM monthly_income WHERE month=?", array(date("Y-m")));

		$row = $query->result_array();

		if($row[0]['total'])
		{
			$this->db->query("UPDATE monthly_income SET amount = amount + ".round($price)." WHERE month=?", array(date("Y-m")));
		}
		else
		{
			$this->db->query("INSERT INTO monthly_income(month, amount) VALUES(?, ?)", array(date("Y-m"), round($price)));
		}
	}
}