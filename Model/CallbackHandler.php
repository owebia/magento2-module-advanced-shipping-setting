<?php
/**
 * Copyright © Owebia. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Owebia\AdvancedShipping\Model;

use Owebia\AdvancedShipping\Model\Wrapper\RateResult as RateResultWrapper;

class CallbackHandler extends \Owebia\SharedPhpConfig\Model\CallbackHandler
{
    /**
     * @var array
     */
    protected $parsingResult = [];

    /**
     * @var string|null
     */
    protected $currentMethodId = null;

    /**
     * @return RateResultWrapper\Method
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function addMethodCallback()
    {
        $args = func_get_args();
        if (count($args) != 1 && count($args) != 2) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __("Invalid arguments count for %1 FuncCall", 'addMethod')
            );
        }

        $methodId = array_shift($args);
        if (!is_string($methodId) || !preg_match('#^[a-z][a-z0-9_]*$#', $methodId)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                __("Invalid first argument for %1 FuncCall: the first argument must be a string and match the following pattern: %2", 'addMethod', '^[a-z][a-z0-9_]*$')
            );
        }

        $this->currentMethodId = $methodId;

        $methodOptions = array_shift($args);
        if (empty($methodOptions)) {
            $methodOptions = [
                'title' => 'Method Title',
                'enabled' => true,
            ];
        } else {
            if (!is_array($methodOptions)) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __("Invalid second argument for addMethod FuncCall: the second argument must be an array")
                );
            }

            if (isset($this->parsingResult[$methodId])) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __("The method %1 already exists", $methodId)
                );
            }

            $methodOptions = $methodOptions + [ 'enabled' => true ];
            $price = isset($methodOptions['price']) ? $methodOptions['price'] : null;
            if ($price === null | $price === false) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __("Invalid price")
                );
            }
        }

        /** @var \Owebia\AdvancedShipping\Model\Wrapper\RateResult\Method $method */
        $method = $this->registry->create(RateResultWrapper\Method::class, [ 'data' => $methodOptions ]);
        $method->setId($methodId);
        $this->parsingResult[$methodId] = $method;
        $this->currentMethodId = null;
        return $method;
    }

    /**
     * @return RateResultWrapper\Error
     */
    public function appendParsingError($message)
    {
        return $this->addError($message, $this->currentMethodId);
    }

    /**
     * @return RateResultWrapper\Error
     */
    protected function addError($message, $id = null)
    {
        $data = [ 'error' => $message ];
        /** @var \Owebia\AdvancedShipping\Model\Wrapper\RateResult\Error $error */
        $error = $this->registry->create(RateResultWrapper\Error::class, [ 'data' => $data ]);
        if (!empty($id)) {
            $error->setId($id);
            $this->parsingResult[$id] = $error;
        } else {
            $this->parsingResult[] = $error;
        }

        return $error;
    }

    /**
     * @return RateResultWrapper\Error
     */
    public function addErrorCallback($message)
    {
        return $this->addError($message);
    }

    /**
     * @return array
     */
    public function getMethodsCallback($onlyEnabled = false)
    {
        $methods = [];
        foreach ($this->parsingResult as $item) {
            if ($item instanceof RateResultWrapper\Method && (!$onlyEnabled || $item->enabled)) {
                $methods[] = $item;
            }
        }

        return $methods;
    }

    /**
     * @return array
     */
    public function getParsingResult()
    {
        return $this->parsingResult;
    }
}
