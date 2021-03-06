<?php

namespace Nexio\Payment\Controller\Checkout;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\ConfigInterface;
/**
 * Class AbstractCheckoutController
 * @package Nexio\Payment\Controller\Checkout
 */
abstract class AbstractCheckoutController extends Action
{
    /**
     * @var
     */
    protected $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var CommandPoolInterface
     */
    protected $commandPool;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var \Nexio\Payment\Logger\Logger
     */
    protected $logger;

    protected $encryptor;

    protected $checkoutsession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    protected $transactionFactory;
    /**
     * AbstractCheckoutController constructor.
     * @param Context $context
     * @param ConfigInterface $config
     * @param CustomerSession $customerSession
     * @param CommandPoolInterface $commandPool
     * @param \Nexio\Payment\Logger\Logger $logger
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        Context $context,
        ConfigInterface $config,
        CustomerSession $customerSession,
        CommandPoolInterface $commandPool,
        \Nexio\Payment\Logger\Logger $logger,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory
    ) {
        $this->customerSession = $customerSession;
        $this->commandPool = $commandPool;
        $this->registry = $registry;
        $this->logger = $logger;
        $this->config = $config;
        $this->encryptor = $encryptor;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->transactionFactory = $transactionFactory;
        parent::__construct($context);
    }
}

