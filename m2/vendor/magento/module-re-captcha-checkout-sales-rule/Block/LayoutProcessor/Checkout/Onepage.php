<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\ReCaptchaCheckoutSalesRule\Block\LayoutProcessor\Checkout;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Magento\Framework\Exception\InputException;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\UiConfigResolverInterface;

/**
 * Provides reCaptcha component configuration.
 */
class Onepage implements LayoutProcessorInterface
{
    /**
     * @var UiConfigResolverInterface
     */
    private $captchaUiConfigResolver;

    /**
     * @var IsCaptchaEnabledInterface
     */
    private $isCaptchaEnabled;

    /**
     * @param UiConfigResolverInterface $captchaUiConfigResolver
     * @param IsCaptchaEnabledInterface $isCaptchaEnabled
     */
    public function __construct(
        UiConfigResolverInterface $captchaUiConfigResolver,
        IsCaptchaEnabledInterface $isCaptchaEnabled
    ) {
        $this->captchaUiConfigResolver = $captchaUiConfigResolver;
        $this->isCaptchaEnabled = $isCaptchaEnabled;
    }

    /**
     * @inheritDoc
     */
    public function process($jsLayout)
    {
        $key = 'coupon_code';
        if ($this->isCaptchaEnabled->isCaptchaEnabledFor($key)) {
            $jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']
            ['payment']['children']['afterMethods']['children']['discount']['children']
            ['checkout_sales_rule']['settings'] = $this->captchaUiConfigResolver->get($key);
        } else {
            if (isset($jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']
                ['payment']['children']['afterMethods']['children']['discount']['children']['checkout_sales_rule'])) {
                unset($jsLayout['components']['checkout']['children']['steps']['children']['billing-step']['children']
                    ['payment']['children']['afterMethods']['children']['discount']['children']['checkout_sales_rule']);
            }
        }

        return $jsLayout;
    }
}
