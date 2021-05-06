<?php

namespace Test\Integration;

require_once dirname(__DIR__) . '/Setup.php';

use DateTime;
use Test;
use Test\Setup;
use Braintree;

class UsBankAccountTransactionTest extends Setup
{
    public function testSaleWithUsBankAccountNonce()
    {
        $result = Braintree\Transaction::sale([
            'amount' => '100.00',
            'merchantAccountId' => Test\Helper::usBankMerchantAccount(),
            'paymentMethodNonce' => Test\Helper::generateValidUsBankAccountNonce(),
            'options' => [
                'submitForSettlement' => true,
                'storeInVault' => true
            ]
        ]);

        $this->assertTrue($result->success);
        $transaction = $result->transaction;
        $this->assertEquals(Braintree\Transaction::SETTLEMENT_PENDING, $transaction->status);
        $this->assertEquals(Braintree\Transaction::SALE, $transaction->type);
        $this->assertEquals('100.00', $transaction->amount);
        $this->assertEquals('021000021', $transaction->usBankAccount->routingNumber);
        $this->assertEquals('1234', $transaction->usBankAccount->last4);
        $this->assertEquals('checking', $transaction->usBankAccount->accountType);
        $this->assertEquals('Dan Schulman', $transaction->usBankAccount->accountHolderName);
        $this->assertMatchesRegularExpression('/CHASE/', $transaction->usBankAccount->bankName);
        $this->assertEquals('cl mandate text', $transaction->usBankAccount->achMandate->text);
        $this->assertEquals('DateTime', get_class($transaction->usBankAccount->achMandate->acceptedAt));
    }

    public function testSaleWithUsBankAccountNonceAndVaultedToken()
    {
        $result = Braintree\Transaction::sale([
            'amount' => '100.00',
            'merchantAccountId' => Test\Helper::usBankMerchantAccount(),
            'paymentMethodNonce' => Test\Helper::generateValidUsBankAccountNonce(),
            'options' => [
                'submitForSettlement' => true,
                'storeInVault' => true
            ]
        ]);

        $this->assertTrue($result->success);
        $transaction = $result->transaction;
        $this->assertEquals(Braintree\Transaction::SETTLEMENT_PENDING, $transaction->status);
        $this->assertEquals(Braintree\Transaction::SALE, $transaction->type);
        $this->assertEquals('100.00', $transaction->amount);
        $this->assertEquals('021000021', $transaction->usBankAccount->routingNumber);
        $this->assertEquals('1234', $transaction->usBankAccount->last4);
        $this->assertEquals('checking', $transaction->usBankAccount->accountType);
        $this->assertEquals('Dan Schulman', $transaction->usBankAccount->accountHolderName);
        $this->assertEquals('cl mandate text', $transaction->usBankAccount->achMandate->text);
        $this->assertEquals('DateTime', get_class($transaction->usBankAccount->achMandate->acceptedAt));

        $result = Braintree\Transaction::sale([
            'amount' => '100.00',
            'merchantAccountId' => Test\Helper::usBankMerchantAccount(),
            'paymentMethodToken' => $transaction->usBankAccount->token,
            'options' => [
                'submitForSettlement' => true,
                'storeInVault' => true
            ]
        ]);
        $this->assertTrue($result->success);
        $transaction = $result->transaction;
        $this->assertEquals(Braintree\Transaction::SETTLEMENT_PENDING, $transaction->status);
        $this->assertEquals(Braintree\Transaction::SALE, $transaction->type);
        $this->assertEquals('100.00', $transaction->amount);
        $this->assertEquals('021000021', $transaction->usBankAccount->routingNumber);
        $this->assertEquals('1234', $transaction->usBankAccount->last4);
        $this->assertEquals('checking', $transaction->usBankAccount->accountType);
        $this->assertEquals('Dan Schulman', $transaction->usBankAccount->accountHolderName);
        $this->assertEquals('cl mandate text', $transaction->usBankAccount->achMandate->text);
        $this->assertEquals('DateTime', get_class($transaction->usBankAccount->achMandate->acceptedAt));
    }

    public function testSaleWithPlaidUsBankAccountNonce()
    {
        $this->markTestSkipped('Skipping until we have a more stable CI env');
        $result = Braintree\Transaction::sale([
            'amount' => '100.00',
            'merchantAccountId' => Test\Helper::usBankMerchantAccount(),
            'paymentMethodNonce' => Test\Helper::generatePlaidUsBankAccountNonce(),
            'options' => [
                'submitForSettlement' => true,
                'storeInVault' => true
            ]
        ]);

        $this->assertTrue($result->success);
        $transaction = $result->transaction;
        $this->assertEquals(Braintree\Transaction::SETTLEMENT_PENDING, $transaction->status);
        $this->assertEquals(Braintree\Transaction::SALE, $transaction->type);
        $this->assertEquals('100.00', $transaction->amount);
        $this->assertEquals('011000015', $transaction->usBankAccount->routingNumber);
        $this->assertEquals('0000', $transaction->usBankAccount->last4);
        $this->assertEquals('checking', $transaction->usBankAccount->accountType);
        $this->assertEquals('Dan Schulman', $transaction->usBankAccount->accountHolderName);
        $this->assertMatchesRegularExpression('/FEDERAL/', $transaction->usBankAccount->bankName);
        $this->assertEquals('cl mandate text', $transaction->usBankAccount->achMandate->text);
        $this->assertEquals('DateTime', get_class($transaction->usBankAccount->achMandate->acceptedAt));
    }

    public function testSaleWithInvalidUsBankAccountNonce()
    {
        $result = Braintree\Transaction::sale([
            'amount' => '100.00',
            'merchantAccountId' => Test\Helper::usBankMerchantAccount(),
            'paymentMethodNonce' => Test\Helper::generateInvalidUsBankAccountNonce(),
            'options' => [
                'submitForSettlement' => true,
                'storeInVault' => true
            ]
        ]);

        $this->assertFalse($result->success);
        $baseErrors = $result->errors->forKey('transaction')->onAttribute('paymentMethodNonce');
        $this->assertEquals(Braintree\Error\Codes::TRANSACTION_PAYMENT_METHOD_NONCE_UNKNOWN, $baseErrors[0]->code);
    }

    public function testCompliantMerchantUnverifiedToken()
    {
        Test\Helper::integration2MerchantConfig();

        $customer = Braintree\Customer::create([
            'firstName' => 'Joe',
            'lastName' => 'Brown'
        ])->customer;

        $result = Braintree\PaymentMethod::create([
            'customerId' => $customer->id,
            'paymentMethodNonce' => Test\Helper::generateValidUsBankAccountNonce(),
            'options' => [
                'verificationMerchantAccountId' => Test\Helper::anotherUsBankMerchantAccount()
            ]
        ]);

        $usBankAccount = $result->paymentMethod;
        $this->assertEquals('021000021', $usBankAccount->routingNumber);
        $this->assertEquals('1234', $usBankAccount->last4);
        $this->assertEquals('checking', $usBankAccount->accountType);
        $this->assertEquals('Dan Schulman', $usBankAccount->accountHolderName);
        $this->assertMatchesRegularExpression('/CHASE/', $usBankAccount->bankName);
        $this->assertEquals(false, $usBankAccount->verified);

        $this->assertEquals(0, count($usBankAccount->verifications));

        $sale = Braintree\Transaction::sale([
            'amount' => '100.00',
            'merchantAccountId' => Test\Helper::anotherUsBankMerchantAccount(),
            'paymentMethodToken' => $usBankAccount->token,
            'options' => [
                'submitForSettlement' => true,
                'storeInVault' => true
            ]
        ]);

        $this->assertFalse($sale->success);
        $baseErrors = $sale->errors->forKey('transaction')->onAttribute('paymentMethodToken');
        $this->assertEquals(Braintree\Error\Codes::TRANSACTION_US_BANK_ACCOUNT_NOT_VERIFIED, $baseErrors[0]->code);
        self::integrationMerchantConfig();
    }

    public function testCompliantMerchantUnverifiedNonce()
    {
        Test\Helper::integration2MerchantConfig();
        $sale = Braintree\Transaction::sale([
            'amount' => '100.00',
            'merchantAccountId' => Test\Helper::anotherUsBankMerchantAccount(),
            'paymentMethodNonce' => Test\Helper::generateValidUsBankAccountNonce(),
            'options' => [
                'submitForSettlement' => true,
                'storeInVault' => true
            ]
        ]);

        $this->assertFalse($sale->success);
        $baseErrors = $sale->errors->forKey('transaction')->onAttribute('paymentMethodNonce');
        $this->assertEquals(Braintree\Error\Codes::TRANSACTION_US_BANK_ACCOUNT_NONCE_MUST_BE_PLAID_VERIFIED, $baseErrors[0]->code);
        self::integrationMerchantConfig();
    }

    public function testCompliantMerchantPlaidToken()
    {
        $this->markTestSkipped('Skipping until we have a more stable CI env');
        Test\Helper::integration2MerchantConfig();
        $customer = Braintree\Customer::create([
            'firstName' => 'Joe',
            'lastName' => 'Brown'
        ])->customer;

        $result = Braintree\PaymentMethod::create([
            'customerId' => $customer->id,
            'paymentMethodNonce' => Test\Helper::generatePlaidUsBankAccountNonce(),
            'options' => [
                'verificationMerchantAccountId' => Test\Helper::anotherUsBankMerchantAccount()
            ]
        ]);

        $usBankAccount = $result->paymentMethod;
        $this->assertEquals('011000015', $usBankAccount->routingNumber);
        $this->assertEquals('0000', $usBankAccount->last4);
        $this->assertEquals('checking', $usBankAccount->accountType);
        $this->assertEquals('Dan Schulman', $usBankAccount->accountHolderName);
        $this->assertMatchesRegularExpression('/FEDERAL/', $usBankAccount->bankName);
        $this->assertEquals(true, $usBankAccount->verified);

        $this->assertEquals(1, count($usBankAccount->verifications));

        $sale = Braintree\Transaction::sale([
            'amount' => '100.00',
            'merchantAccountId' => Test\Helper::anotherUsBankMerchantAccount(),
            'paymentMethodToken' => $usBankAccount->token,
            'options' => [
                'submitForSettlement' => true,
                'storeInVault' => true
            ]
        ]);

        $this->assertTrue($sale->success);
        $this->assertEquals($sale->transaction->amount, '100.00');
        $this->assertEquals($sale->transaction->usBankAccount->token, $usBankAccount->token);
        self::integrationMerchantConfig();
    }

    public function testCompliantMerchantPlaidNonce()
    {
        $this->markTestSkipped('Skipping until we have a more stable CI env');
        Test\Helper::integration2MerchantConfig();

        $sale = Braintree\Transaction::sale([
            'amount' => '100.00',
            'merchantAccountId' => Test\Helper::anotherUsBankMerchantAccount(),
            'paymentMethodNonce' => Test\Helper::generatePlaidUsBankAccountNonce(),
            'options' => [
                'submitForSettlement' => true,
                'storeInVault' => true
            ]
        ]);

        $this->assertTrue($sale->success);
        $this->assertEquals($sale->transaction->amount, '100.00');
        self::integrationMerchantConfig();
    }
}
