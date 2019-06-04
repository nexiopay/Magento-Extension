<?php

namespace Nexio\Payment\Controller\Checkout;

use Nexio\Payment\Gateway\Http\TransferFactory;
use Nexio\Payment\Gateway\Http\Client\TransactionGetOneTimeUseToken as TransactionGetOTUT;
use Magento\Framework\Controller\ResultFactory;

/**
 * Class GetSecretConfig
 * @package Nexio\Payment\Controller\Checkout
 */
class GetSecretConfig extends AbstractCheckoutController
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     * this link can only be called from internal
     */
    public function execute()
    {
        $this->logger->addDebug('Get Secret config is called!!');	   
        $orderId = 27;
        $order = $orderId ? $this->orderFactory->create()->load($orderId) : false;
        if(!$order)
        {
            $this->logger->addDebug('no order');	
        }
        $orderNum = $order->getIncrementId();
        $this->logger->addDebug('OrderNum: '.$orderNum);
        $order->setState('processing');
        $order->setStatus('processing');
        $payment = $order->getPayment();
        $refnum = 'test_ref_000';
        $payment->setCcTransId($refnum);
        $payment->setLastTransId($refnum);
        $payment->setTransactionId($refnum);
        $payment->setShouldCloseParentTransaction(false);
        $payment->setIsTransactionClosed(false);
        try
        {
            $invoice = $order->prepareInvoice();
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register();

            if($payment->canCapture())
            {
                $this->logger->addDebug('payment can capture');
                $payment->capture();
            }
            else
            {
                $this->logger->addDebug('payment can not capture!!');
            }

            if(is_null($this->transactionFactory) || empty($this->transactionFactory)) 
            {
                $this->logger->addDebug('transactionFactory is null');
            }
            else
            {
                $this->logger->addDebug('transactionFactory is not null!!'); 
            }
            $transaction = $this->transactionFactory->create();
            $transaction->addObject($invoice);
            $transaction->addObject($invoice->getOrder());
    
            $transaction->save();
        }
        catch(Exception $e)
        {
            $this->logger->addDebug('create and save invoice get exception: '.$e->getMessage());
        }
        


        $command = 'getsecret';

        if(!empty($_GET["command"]) && isset($_GET["command"]))
        {
            //there is command, use the passed value
            $command = $_GET["command"];
            $this->logger->addDebug('parameter: '. $_GET["command"]);
        }

        if(is_null($this->checkoutSession))
        {
            $this->logger->addDebug('checkout session is null');
        }
        else
        {
            $this->logger->addDebug('checkout session is not null!!'); 
        }

        //$order = $this->checkoutSession->getLastRealOrder();
        //$orderId=$order->getEntityId();
        
        $var = "error";

        if($command === 'updatesecret')
        {
            $var = $this->update_secret();
        }
        else
        {
            $var = $this->get_secret();
        }

        $response = array(
            "secret" => $var
        );
        echo json_encode($response);   
    }

    /**
	 * get_secret
	 * get the share secret of merchant
	 * @since 0.0.5
	 * @return string
	 * 
	 */
    
	private function get_secret()
	{
		try {
			$basicauth = $this->getAuthorization();
            $this->logger->addDebug("basicauth is: ".$basicauth);
            $requesturl = $this->getUrl("/webhook/v3/secret/").$this->getMerchantId();
            $this->logger->addDebug("requesturl is: ".$requesturl);
			$ch = curl_init($requesturl);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
			
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Authorization: $basicauth",
				"Content-Type: application/json"));
			$result = curl_exec($ch);
			$error = curl_error($ch);
			curl_close($ch);
			
			$this->logger->addDebug('get secret response: '.$result);
			if ($error) {
				$this->logger->addDebug("get secret get error, return error");
				return "error";
			} else {
                if(!empty(json_decode($result)->error) || empty(json_decode($result)->secret))
                {
                    $this->logger->addDebug("no correct message, return error");
				    return "error";
                }
				
				$secret = json_decode($result)->secret;
				error_log('get secret: '.$secret);
				return $secret;
			}
		} catch (Exception $e) {
			
			$this->logger->addDebug("Get secret failed:".$e->getMessage(),0);
			return "error";
		}
    }
    
   /**
	 * get_secret
	 * get the share secret of merchant
	 * @since 0.0.5
	 * @return string
	 * 
	 */
    
	private function update_secret()
	{
		try {
            $basicauth = $this->getAuthorization();
            $request = array(
                'merchantId' => $this->getMerchantId()
            );
            $data = json_encode($request);
            $this->logger->addDebug("update secret basicauth is: ".$basicauth);
            $requesturl = $this->getUrl("/webhook/v3/secret");
            $this->logger->addDebug("udate secret requesturl is: ".$requesturl);
			$ch = curl_init($requesturl);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Authorization: $basicauth",
                "Content-Type: application/json",
                "Content-Length: " . strlen($data)));
			$result = curl_exec($ch);
			$error = curl_error($ch);
			curl_close($ch);
			
			$this->logger->addDebug('get update secret response: '.$result);
			if ($error) {
				$this->logger->addDebug("get secret get error, return error");
				return "error";
			} else {
                if(!empty(json_decode($result)->error) || empty(json_decode($result)->secret))
                {
                    $this->logger->addDebug("update secret no correct message, return error");
				    return "error";
                }
				
				$secret = json_decode($result)->secret;
				error_log('get secret: '.$secret);
				return $secret;
			}
		} catch (Exception $e) {
			
			$this->logger->addDebug("update secret failed:".$e->getMessage(),0);
			return "error";
		}
    }


    private function getMerchantId()
    {
        return $this->config->getValue('merchant_id');
    }

    /**
     * @return string
     */
    private function getAuthorization()
    {
        return 'Basic ' . base64_encode($this->getUsername() . ':' . $this->getPassword());
    }

    /**
     * @return string
     */
    private function getUsername()
    {
        $isTest = $this->getIsTest();
        return $isTest ? $this->config->getValue('test_username') : $this->config->getValue('username');
    }

    /**
     * @return string
     */
    private function getPassword()
    {
        $isTest = $this->getIsTest();
        $pw = $isTest ? $this->config->getValue('test_password') : $this->config->getValue('password');
        return $this->encryptor->decrypt($pw);
    }

    /**
     * @return bool
     */
    private function getIsTest()
    {
        return !!$this->config->getValue('is_test');
    }

    /**
     * Get request URL
     *
     * @param string $additionalPath
     * @return string
     */
    public function getUrl($additionalPath = '')
    {
        $isTest = $this->getIsTest();

        $uri = $isTest ? $this->config->getValue('test_endpoint') : $this->config->getValue('endpoint');
        $uri = trim($uri);
        $uri = rtrim($uri, '/');
        $url = $uri . $additionalPath;
        return $url;
    }

}

