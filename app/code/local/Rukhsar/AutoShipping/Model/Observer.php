<?php

class Rukhsar_AutoShipping_Model_Observer {

    /**
     * add shipping charge to cart
     *
     * @param Varien_Event_Observer $observer
     * @return \Rukhsar_AutoShipping_Model_Observer
     */
    public function addShipping($observer) {

        $checkout = Mage::getSingleton('checkout/session');
        $quote = $checkout->getQuote();
        $shippingAddress = $quote->getShippingAddress();

        //first use the default Country code
        $country = Mage::getStoreConfig('general/country/default');

        //check if the customer has logged in
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            if ($customer->getPrimaryShippingAddress() && $customer->getPrimaryShippingAddress()->getCountry()) {

                //use customer's shipping address country if there's one
                $country = $customer->getPrimaryShippingAddress()->getCountry();
            }
        }

        //only set country if it does not exists already
        if (!$shippingAddress->getCountryId()) {
            $shippingAddress->setCountryId($country);
        }

        //allow shipping rates recalculation
        $shippingAddress->setCollectShippingRates(true);


        //rest qutoe item counts so that shipping calculation is based on the correct quantity
        $quote->collectTotals();

        $shippingAddress->collectShippingRates();

        if ($quote->getItemsCount()) {

            //get available rates
            $rates = $shippingAddress->getGroupedAllShippingRates();

            if (count($rates)) {

                //get the top positioned rate. It's based on the position set up in backend
                $topRate = reset($rates);
                $rateToApply = $topRate[0]->getCode();

                try {

                    //apply shipping
                    $shippingAddress->setShippingMethod($rateToApply);
                    $quote->save();

                    //set checkoutstate to CHECKOUT_STATE_BEGIN
                    //prevent the address from being removed when init() in Mage_Checkout_Model_Cart is called
                    $checkout->resetCheckout();
                } catch (Mage_Core_Exception $e) {
                    $checkout->addError($e->getMessage());
                } catch (Exception $e) {
                    $checkout->addException($e, Mage::helper('checkout')->__('Cannot set shipping method.'));
                    Mage::logException($e);
                }
            }
        }

        return $this;
    }

}