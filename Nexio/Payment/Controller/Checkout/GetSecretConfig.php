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
        
        $command = 'getsecret';
        
        if(!empty($_GET["command"]) && isset($_GET["command"]))
        {
            //there is command, use the passed value
            $command = $_GET["command"];
            $this->logger->addDebug('parameter: '. $_GET["command"]);
        }
        
        
        if($command === 'getsecret')
        {
            $this->logger->addDebug('receive get secret command');
            $return = $this->loadSecret();
            echo json_encode($return);
        }
        else if($command === 'updateorder')
        {
            $this->logger->addDebug('receive update order command');
            $this->logger->addDebug("eventType: ".$_GET["eventType"]);
            $this->logger->addDebug("authCode: ".$_GET["authCode"]);
            $this->logger->addDebug("amount: ".$_GET["amount"]);
            $this->logger->addDebug("orderId: ".$_GET["orderId"]);
            $this->updateOrder($_GET["orderId"],$_GET["amount"],$_GET["authCode"],$_GET["eventType"]);
        }
        else if($command == 'updateorderwitherr')
        {
            $this->logger->addDebug('receive update order with error command');
            $this->logger->addDebug("orderId: ".$_GET["orderId"]);
            $this->logger->addDebug("msg: ".$_GET["msg"]);

            $this->updateOderWithErrorMsg($_GET["orderId"],$_GET["msg"]);
        }
        

        
    }

    private function loadSecret()
    {
        $command = 'getsecret';

        $response = array(
            'verifyflag' =>true,
            'secret' => 'error'
        );

        if($this->getverifysignature())
        {
            //need do signature verification 
            $var = "error";
            $var = $this->get_secret();
            
            if($command === 'updatesecret')
            {
                $var = $this->update_secret();
            }
            else
            {
                $var = $this->get_secret();
                if($var === 'error')
                {
                    //do update secret 
                    $var = $this->update_secret();
                } 
            }
            
            $response = array(
                'verifyflag' =>true,
                'secret' => $var
            );
            
            
        }
        else
        {
            $response = array(
                'verifyflag' =>false,
                'secret' => 'error'
            );
        }
          
        return $response;   
    }

    private function updateOderWithErrorMsg($orderId,$msg)
    {
        $order = $orderId ? $this->orderFactory->create()->load($orderId) : false;
        if(!$order)
        {
            $this->logger->addDebug('updateOrderWithErrorMsg cannot load order');	
        }

        $order->addStatusHistoryComment($msg);
        $order->save();
    }

    private function updateOrder($orderId,$amount,$authCode,$eventType)
    {
        //$orderId = 26;
        $order = $orderId ? $this->orderFactory->create()->load($orderId) : false;
        if(!$order)
        {
            $this->logger->addDebug('no order');	
        }
        $orderNum = $order->getIncrementId();
        $this->logger->addDebug('OrderNum: '.$orderNum);
        $order->setState('processing');
        $order->setStatus('processing');
        $order->addStatusHistoryComment('Webhook function signature verification passed');
        $order->addStatusHistoryComment('Nexio AuthCode is: '.$authCode);
        $payment = $order->getPayment();
        
        //$payment->setCcTransId($refnum);
        //$payment->setLastTransId($refnum);
        //$payment->setTransactionId($refnum);
        $payment->setShouldCloseParentTransaction(false);
        $payment->setIsTransactionClosed(false);

        //todo need judege type is capture or only auth if only auth, do not generate invoice !!!!
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
				$this->logger->addDebug('get secret: '.$secret);
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
     * @return bool
     */
    private function getverifysignature()
    {
        return !!$this->config->getValue('verify_signature');
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


