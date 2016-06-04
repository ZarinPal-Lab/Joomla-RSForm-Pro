<?php
/**
* @package RSMembership!
* @copyright (C) 2014 www.rsjoomla.com
* @license GPL, http://www.gnu.org/licenses/gpl-2.0.html
*/
/**
 * Zarinpal Payment Gateway Plugin for RSForm!Pro Component
 * PHP version 5.6+
 * @author Mohsen Ranjbar (mimrahe) <mimrahe@gmail.com>
 * @copyright 2016-2017 Zarinpal.com
 * @version 1.51.4
 * @link https://github.com/ZarinPal-Lab/
 */

defined('_JEXEC') or die('Restricted access');

class plgSystemRSFPZarinPal extends JPlugin
{
	protected $componentId = 642;
	protected $componentValue = 'zarinpal';

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->newComponents = array(642);
	}

	/*
	 * display zarinpal menu item in page formsazi
	 */
	public function rsfp_bk_onAfterShowComponents()
	{
		JFactory::getLanguage()->load('plg_system_rsfpzarinpal');

		$formId = JFactory::getApplication()->input->getInt('formId');

		$link = "displayTemplate('" . $this->componentId . "')";
		if ($components = RSFormProHelper::componentExists($formId, $this->componentId))
			$link = "displayTemplate('" . $this->componentId . "', '" . $components[0] . "')";
		?>
		<li><a href="javascript: void(0);" onclick="<?php echo $link; ?>;return false;"
			   id="rsfpc<?php echo $this->componentId; ?>"><span class="rsficon rsficon-zarinpal"></span><span
					class="inner-text"><?php echo JText::_('RSFP_ZARINPAL_COMPONENT'); ?></span></a></li>
		<?php
	}

	/*
	 * get what type of payment is ex. paypal or zarinpal
	 */
	public function rsfp_getPayment(&$items, $formId)
	{
		if ($components = RSFormProHelper::componentExists($formId, $this->componentId)) {
			$data = RSFormProHelper::getComponentProperties($components[0]);

			$item = new stdClass();
			$item->value = $this->componentValue;
			$item->text = $data['LABEL'];

			// add to array
			$items[] = $item;
		}
	}

	/*
	 * do payment:
	 * get config and redirect to zarinpal
	 */
	public function rsfp_doPayment($payValue, $formId, $SubmissionId, $price, $products, $code)
	{
		//TODO: price and currency
		//TODO: zarin gate
		$app = JFactory::getApplication();
		try {
			// execute only for our plugin
			if ($payValue != $this->componentValue)
				throw new Exception(JText::_('پلاکین مورد استفاده زرین پال نمی باشد'));
			if ($price <= 0)
				throw new Exception(JText::_('مبلغ نباید منفی یا صفر باشد'));

			$MerchantID = RSFormProHelper::getConfig('zarinpal.merchantid');
			if (empty($MerchantID))
				throw new Exception(JText::_('کد پذیرنده را در قسمت تنظیمات وارد کنید'));

			$Amount = $this->zarinPalAmount($price);
			$Description = JText::_('محصولات فروخته شامل ') . implode(', ', $products);

			list(, $with) = RSFormProHelper::getReplacements($SubmissionId);
			$Email = (empty($with[29])) ? '' : $with[29];
			$Mobile = '';
			if (empty($formId))
				$formId = $app->input->getInt('formId');

			$CallbackURL = JURI::root() . 'index.php?option=com_rsform&formId=' . $formId . '&task=plugin&plugin_task=zarinpal.notify&amount=' . $Amount . '&code=' . $code;

			$context = compact(
				'MerchantID',
				'Amount',
				'Description',
				'Email',
				'Mobile',
				'CallbackURL'
			);

			$zpRequest = $this->zarinPalRequest('Request', $context);
			if (!$zpRequest)
				throw new Exception(JText::_('اشکالی در ارتباط با زرین پال پیش آمده است'));

			$status = $zpRequest->Status;
			if ($status == 100) {
				$authority = $zpRequest->Authority;
				$prefix = RSFormProHelper::getConfig('zarinpal.test') ? 'sandbox' : 'www';
				$postfix = RSFormProHelper::getConfig('zarinpal.gatetype') ? '/ZarinGate' : '';
				$paymentGatewayURL = "https://{$prefix}.zarinpal.com/pg/StartPay/";

				$app->redirect($paymentGatewayURL . $authority . $postfix);
				exit;
			}

			$app->enqueueMessage($this->zarinPalStatusMessage($status), 'error');
			return false;

		} catch (Exception $e) {
			$app->enqueueMessage($e->getMessage(), 'error');
			return;
		}
	}

	/*
	 * detect currency for amount
	 */
	private function zarinPalAmount($price)
	{
		$currency = RSFormProHelper::getConfig('payment.currency');
		switch ($currency)
		{
			case 'ریال':
			case 'IRR':
			case 'RIAL':
			case 'Rial':
			case 'rial':
				$price = (int)($price / 10);
				break;
			case 'تومان':
			case 'IRT':
			case 'TOMAN':
			case 'Toman':
			case 'toman':
			default:
				$price = (int)$price;
				break;
		}
		return $price;
	}

	/*
	 * make a zarinpal soap request
	 */
	private function zarinPalRequest($type, $context)
	{
		try {
			$prefix =  RSFormProHelper::getConfig('zarinpal.test') ? 'sandbox' : 'www';
			$client = new SoapClient("https://{$prefix}.zarinpal.com/pg/services/WebGate/wsdl", array('encoding' => 'UTF-8'));

			$type = 'Payment' . ucfirst($type);
			$result = $client->$type($context);
			return $result;
		} catch (SoapFault $e) {
			return false;
		}
	}

	/*
	 * return related zarinpal status message
	 */
	private function zarinPalStatusMessage($status)
	{
		$status = (string) $status;
		$statusCode = [
			'-1' => 'اطلاعات ارسال شده ناقص است.',
			'-2' => 'IP و یا کد پذیرنده اشتباه است',
			'-3' => 'با توجه به محدودیت های شاپرک امکان پرداخت با رقم درخواست شده میسر نمی باشد',
			'-4' => 'سطح تایید پذیرنده پایین تر از سطح نقره ای است',
			'-11' => 'درخواست موردنظر یافت نشد',
			'-12' => 'امکان ویرایش درخواست میسر نمی باشد',
			'-21' => 'هیچ نوع عملیات مالی برای این تراکنش یافت نشد',
			'-22' => 'تراکنش ناموفق می باشد',
			'-33' => 'رقم تراکنش با رقم پرداخت شده مطابقت ندارد',
			'-34' => 'سقف تقسیم تراکنش از لحاظ تعداد یا رقم عبور نموده است',
			'-40' => 'اجازه دسترسی به متد مربوطه وجود ندارد',
			'-41' => 'اطلاعات ارسالی مربوط به اطلاعات اضافی غیرمعتبر می باشد',
			'-42' => 'مدت زمان معتبر طول عمر شناسه پرداخت باید بین ۳۰ دقیقه تا ۴۵ روز می باشد',
			'-54' => 'درخواست موردنظر آرشیو شده است',
			'101' => 'عملیات پرداخت موفق بوده و قبلا اعتبارسنجی تراکنش انجام شده است'
		];
		if (isset($statusCode[$status])) {
			return $statusCode[$status];
		}
		return 'خطای نامشخص. کد خطا: ' . $status;
	}

	/*
	 * this will set display of zarinpal payment into form
	 */
	public function rsfp_bk_onAfterCreateComponentPreview($args = array())
	{
		if ($args['ComponentTypeName'] == 'zarinpal') {
			$args['out'] = '<td>&nbsp;</td>';
			$args['out'] .= '<td><span style="font-size:24px;margin-right:5px" class="rsficon rsficon-zarinpal"></span> ' . $args['data']['LABEL'] . '</td>';
		}
	}

	/*
	 * this will be done when configuration page displayed
	 * and call function 'zarinPalConfigurationScreen'
	 */
	public function rsfp_bk_onAfterShowConfigurationTabs($tabs)
	{
		JFactory::getLanguage()->load('plg_system_rsfpzarinpal');

		$tabs->addTitle(JText::_('RSFP_ZARINPAL_LABEL'), 'form-zarinpal');
		$tabs->addContent($this->zarinPalConfigurationScreen());
	}

	/*
	 * check to see what to do when back to site from zarinpal
	 */
	public function rsfp_f_onSwitchTasks()
	{
		try {
			$app = JFactory::getApplication();
			$input = $app->input;
			$pluginTask = $input->getString('plugin_task', '');
			if ($pluginTask != 'zarinpal.notify')
				throw new Exception(sprintf("وظیفه %s برای زرین پال تعریف نشده است", $pluginTask));

			if ($input->getString('Status') != 'OK')
				throw new Exception("پرداخت ناموفق بود");

			$Amount = $input->getInt('amount');
			$Authority = $input->getString('Authority');
			$MerchantID = RSFormProHelper::getConfig('zarinpal.merchantid');

			$context = compact('MerchantID', 'Authority', 'Amount');

			$zpVerify = $this->zarinPalRequest('Verification', $context);

			if (!$zpVerify)
				throw new Exception("اشکالی در ارتباط با زرین پال پیش آمده است");

			$status = $zpVerify->Status;
			$root = JURI::root();
			$htmlStart = "<!DOCTYPE html><html><head><meta charset='utf-8' ></head><body>";
			$htmlEnd = "</body></html>";

			if ($status == 100) {
				$RefID = $zpVerify->RefID;
				$db = JFactory::getDBO();
				$code = $input->getCmd('code');
				$formId = $input->getInt('formId');
				$db->setQuery("SELECT SubmissionId FROM #__rsform_submissions s WHERE s.FormId='" . $formId . "' AND MD5(CONCAT(s.SubmissionId,s.DateSubmitted)) = '" . $db->escape($code) . "'");
				if ($SubmissionId = $db->loadResult()) {
					$db->setQuery("UPDATE #__rsform_submission_values sv SET sv.FieldValue=1 WHERE sv.FieldName='_STATUS' AND sv.FormId='" . $formId . "' AND sv.SubmissionId = '" . $SubmissionId . "'");
					$db->execute();

					$app->triggerEvent('rsfp_afterConfirmPayment', array($SubmissionId));
				}
				$message = sprintf(
					"%s<p style='font:bold 16px tahoma; color:darkgreen; direction:rtl; text-align:center;'>پرداخت با موفقیت انجام شد<br>شماره پیگیری تراکنش:<br>%s<br><a href='%s'>برای ادامه کلیک کنید!</a></p>%s",
					$htmlStart,
					$RefID,
					$root,
					$htmlEnd
				);

				echo $message;
				jexit('');
			}

			$status = $this->zarinPalStatusMessage($status);
			$message = sprintf(
				"%s<p style='font:bold 16px tahoma; color:darkred; direction:rtl; text-align:center;'>پرداخت ناموفق بود<br>خطای پیش آمده:<br>%s<br><a href='%s'>برای ادامه کلیک کنید!</a></p>%s",
				$htmlStart,
				$status,
				$root,
				$htmlEnd
			);

			echo $message;

		} catch (Exception $e) {
			echo $e->getMessage();
			exit;
		}
	}

	/*
	 * create zarinpal configuration page
	 */
	private function zarinPalConfigurationScreen()
	{
		ob_start();

		?>
		<div id="page-zarinpal" class="com-rsform-css-fix">
			<table class="admintable">
				<tr>
					<td width="200" style="width: 200px;" align="right" class="key"><label
							for="merchantID"><?php echo JText::_('RSFP_ZARINPAL_MERCHANT_ID'); ?></label></td>
					<td><input id="merchantID" type="text" name="rsformConfig[zarinpal.merchantid]"
							   value="<?php echo RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('zarinpal.merchantid')); ?>"
							   size="100" maxlength="64"></td>
				</tr>
				<tr>
					<td width="200" style="width: 200px;" align="right" class="key"><label
							for="test"><?php echo JText::_('RSFP_ZARINPAL_TEST'); ?></label></td>
					<td><?php echo JHTML::_('select.booleanlist', 'rsformConfig[zarinpal.test]', '', RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('zarinpal.test'))); ?></td>
				</tr>
				<tr>
					<td width="200" style="width: 200px;" align="right" class="key"><label
							for="gatetype"><?php echo JText::_('RSFP_ZARINPAL_GATE_TYPE'); ?></label></td>
					<td><?php echo JHTML::_('select.booleanlist', 'rsformConfig[zarinpal.gatetype]', '', RSFormProHelper::htmlEscape(RSFormProHelper::getConfig('zarinpal.gatetype')), 'زرین گیت', 'وب گیت'); ?></td>
				</tr>
			</table>
		</div>
		<?php

		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}
}