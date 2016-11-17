<?php
/**
 * Created by PhpStorm.
 * User: desarrollador1
 * Date: 5/01/16
 * Time: 04:15 PM
 */

namespace PayU\PayUBundle\Services;

use PayU\PayUBundle\lib\PayU;
use PayU\PayUBundle\lib\PayU\api\Environment;
use PayU\PayUBundle\lib\PayU\PayUReports;
use PayU\PayUBundle\lib\PayU\util\PayUParameters;
use PayU\PayUBundle\lib\PayU\PayUTokens;
use PayU\PayUBundle\lib\PayU\PayUPayments;
use PayU\PayUBundle\lib\PayU\api\SupportedLanguages;
use PayU\PayUBundle\lib\PayU\api\PaymentMethods;
use PayU\PayUBundle\lib\PayU\PayUSubscriptionPlans;
use PayU\PayUBundle\lib\PayU\PayUCustomers;
use PayU\PayUBundle\lib\PayU\PayUCreditCards;
use PayU\PayUBundle\lib\PayU\PayUSubscriptions;
use PayU\PayUBundle\lib\PayU\PayURecurringBillItem;
use PayU\PayUBundle\lib\PayU\api\PayUCountries;

class Pago
{
    public function setFirstData($apikey, $apilogin, $merchantId, $isTest = false)
    {
        PayU::$apiKey = $apikey;
        PayU::$apiLogin = $apilogin; //Ingrese aquí su propio apiLogin.
        PayU::$merchantId = $merchantId; //Ingrese aquí su Id de Comercio.
        PayU::$language = SupportedLanguages::ES; //Seleccione el idioma.
        PayU::$isTest = $isTest; //Dejarlo True cuando sean pruebas.

        //var_dump($apikey);die;
        if($isTest) {
            // URL de Pagos
            Environment::setPaymentsCustomUrl("https://sandbox.api.payulatam.com/payments-api/4.0/service.cgi");
            // URL de Consultas
            Environment::setReportsCustomUrl("https://sandbox.api.payulatam.com/reports-api/4.0/service.cgi");
            // URL de Suscripciones para Pagos Recurrentes
            Environment::setSubscriptionsCustomUrl("https://sandbox.api.payulatam.com/payments-api/rest/v4.3/");
        }else{
            Environment::setPaymentsCustomUrl("https://api.payulatam.com/payments-api/4.0/service.cgi");
            Environment::setReportsCustomUrl("https://api.payulatam.com/reports-api/4.0/service.cgi");
            Environment::setSubscriptionsCustomUrl("https://api.payulatam.com/payments-api/rest/v4.3/");
        }
    }

    public function doPing()
    {
        $response = PayUReports::doPing();
        return $response['code'];
    }

    /**
     * Consulta de orden por identificador ej "44469220"
     * @param $orderId
     * @return mixed
     */
    public function getOrderById($orderId)
    {
        $parameters = array(PayUParameters::ORDER_ID => $orderId);

        $order = PayUReports::getOrderDetail($parameters);

        if ($order) {
            $order->accountId;
            $order->status;
            $order->referenceCode;
            $order->additionalValues->TX_VALUE->value;
            $order->additionalValues->TX_TAX->value;
            if ($order->buyer) {
                $order->buyer->emailAddress;
                $order->buyer->fullName;
            }
        }
        // No sé qué retornar
        return $order;
    }

    /**
     * Consulta de orden por referencia ej. "2014-05-06 06:14:19"
     * @param $referenceCode
     * @return null
     */
    public function getOrderByReference($referenceCode)
    {
        $parameters = array(PayUParameters::REFERENCE_CODE => $referenceCode);

        $response = PayUReports::getOrderDetailByReferenceCode($parameters);

        foreach ($response as $order) {
            $order->accountId;
            $order->status;
            $order->referenceCode;
            $order->additionalValues->TX_VALUE->value;
            $order->additionalValues->TX_TAX->value;

            if ($order->buyer) {
                $order->buyer->emailAddress;
            }
        }
        //pendiente de retornar algo
        return null;
    }

    /**
     * Consultar el estado de la transacción por su id ej. "960b1a5d-575d-4bd9-927e-0ffbf5dc4296"
     *
     * @param $transactionId
     * @return mixed
     */
    public function getTransactionState($transactionId)
    {
        $parameters = array(PayUParameters::TRANSACTION_ID => $transactionId);

        $response = PayUReports::getTransactionResponse($parameters);

        if ($response) {
            $response->state;
            $response->trazabilityCode;
            $response->authorizationCode;
            $response->responseCode;
            $response->operationDate;
        }
        //pendiente de validar
        return $response;
    }

    /**
     * Para guardar los datos de una tarjeta de crédito de un cliente, debe devolver un token!
     *
     * @param $fullName
     * @param $userId
     * @param $userDoc
     * @param $creditCardNumber
     * @param $creditCardExpiration
     * @param string $paymentMethod
     * @return null
     */
    public function individualRegister($fullName, $userId, $userDoc, $creditCardNumber, $creditCardExpiration, $paymentMethod='VISA')
    {
        switch ($paymentMethod){
            case 'VISA':
                $metodo= PaymentMethods::VISA;
                break;
            case 'MASTERCARD':
                $metodo= PaymentMethods::MASTERCARD;
                break;
            case 'AMEX':
                $metodo= PaymentMethods::AMEX;
                break;
            case 'DINERS':
                $metodo= PaymentMethods::DINERS;
                break;
            default:
                $metodo= PaymentMethods::DINERS;
                break;
        }
        $parameters = array(
            //Ingrese aquí el nombre del pagador.
            PayUParameters::PAYER_NAME => $fullName,
            //Ingrese aquí el identificador del pagador.
            PayUParameters::PAYER_ID =>$userId,
            //Ingrese aquí el documento de identificación del comprador.
            PayUParameters::PAYER_DNI => $userDoc,
            //Ingrese aquí el número de la tarjeta de crédito
            PayUParameters::CREDIT_CARD_NUMBER => $creditCardNumber,
            //Ingrese aquí la fecha de vencimiento de la tarjeta de crédito
            PayUParameters::CREDIT_CARD_EXPIRATION_DATE => $creditCardExpiration,
            //Ingrese aquí el nombre de la tarjeta de crédito
            PayUParameters::PAYMENT_METHOD => $metodo
        );
        $response = PayUTokens::create($parameters);
        if($response){
            //podrás obtener el token de la tarjeta
            $token= $response['creditCardToken']['creditCardTokenId'];
            return $token;
        }
        return null;
    }

    /**
     * @param $accountId String Identificador de la cuenta del usuario para cada país que tenga asociado el comercio, al enviarla se despliegan solo los medios de pago pertenecientes a dicho país.
     * @param $referenceCode String Es la referencia de la venta o pedido. Deber ser único por cada transacción que se envía al sistema.
     * @param $description
     * @param $value
     * @param string $currency
     * @param $fullnamePayer
     * @param $emailPayer
     * @param $phonePayer
     * @param $payerdni
     * @param $addressPayer
     * @param $statePayer
     * @param $cityPayer
     * @param string $countryPayer
     * @param $tokenId
     * @param $installmentsNumber
     * @param $paymentMethod String Franquicia de la tarjeta  VISA||MASTERCARD||AMEX||DINERS
     * @return mixed
     */
    public function individualBill($accountId, $referenceCode, $description, $value, $currency='COP', $fullnamePayer, $emailPayer, $phonePayer, $payerdni, $addressPayer, $statePayer, $cityPayer, $countryPayer='CO', $tokenId, $paymentMethod, $installmentsNumber)
    {
        switch ($paymentMethod){
            case 'VISA':
                $metodo= PaymentMethods::VISA;
                break;
            case 'MASTERCARD':
                $metodo= PaymentMethods::MASTERCARD;
                break;
            case 'AMEX':
                $metodo= PaymentMethods::AMEX;
                break;
            case 'DINERS':
                $metodo= PaymentMethods::DINERS;
                break;
            default:
                $metodo= PaymentMethods::DINERS;
                break;
        }
        $parameters = array(
            //Ingrese aquí el identificador de la cuenta.
            PayUParameters::ACCOUNT_ID => $accountId,
            //Ingrese aquí el código de referencia.
            PayUParameters::REFERENCE_CODE => $referenceCode,
            //Ingrese aquí la descripción.
            PayUParameters::DESCRIPTION => $description,

            // -- Valores --
            //Ingrese aquí el valor.
            PayUParameters::VALUE => $value,
            //Ingrese aquí la moneda.
            PayUParameters::CURRENCY => $currency,

            /* -- Comprador
            //Ingrese aquí el nombre del comprador.
            PayUParameters::BUYER_NAME => "First name and second buyer name",
            //Ingrese aquí el email del comprador.
            PayUParameters::BUYER_EMAIL => "buyer_test@test.com",
            //Ingrese aquí el teléfono de contacto del comprador.
            PayUParameters::BUYER_CONTACT_PHONE => "7563126",
            //Ingrese aquí el documento de contacto del comprador.
            PayUParameters::BUYER_DNI => "5415668464654",
            //Ingrese aquí la dirección del comprador.
            PayUParameters::BUYER_STREET => "calle 100",
            PayUParameters::BUYER_STREET_2 => "5555487",
            PayUParameters::BUYER_CITY => "Medellin",
            PayUParameters::BUYER_STATE => "Antioquia",
            PayUParameters::BUYER_COUNTRY => "CO",
            PayUParameters::BUYER_POSTAL_CODE => "000000",
            PayUParameters::BUYER_PHONE => "7563126", */

            // -- pagador --
            //Ingrese aquí el nombre del pagador.
            PayUParameters::PAYER_NAME => $fullnamePayer,
            //Ingrese aquí el email del pagador.
            PayUParameters::PAYER_EMAIL => $emailPayer,
            //Ingrese aquí el teléfono de contacto del pagador.
            PayUParameters::PAYER_CONTACT_PHONE => $phonePayer,
            //Ingrese aquí el documento de contacto del pagador.
            PayUParameters::PAYER_DNI => $payerdni,
            //Ingrese aquí la dirección del pagador.
            PayUParameters::PAYER_STREET => $addressPayer,
            //PayUParameters::PAYER_STREET_2 => "125544",
            PayUParameters::PAYER_CITY => $cityPayer,
            PayUParameters::PAYER_STATE => $statePayer,
            PayUParameters::PAYER_COUNTRY => $countryPayer,
            //PayUParameters::PAYER_POSTAL_CODE => "000000",
            PayUParameters::PAYER_PHONE => $phonePayer,

            //DATOS DEL TOKEN
            PayUParameters::TOKEN_ID => $tokenId,

            //Ingrese aquí el nombre de la tarjeta de crédito
            //PaymentMethods::VISA||PaymentMethods::MASTERCARD||PaymentMethods::AMEX||PaymentMethods::DINERS
            PayUParameters::PAYMENT_METHOD => $metodo,

            //Ingrese aquí el número de cuotas.
            PayUParameters::INSTALLMENTS_NUMBER =>$installmentsNumber,
            //Ingrese aquí el nombre del pais.
            PayUParameters::COUNTRY => PayUCountries::CO,

            /*Session id del device.
            PayUParameters::DEVICE_SESSION_ID => "vghs6tvkcle931686k1900o6e1",
            //IP del pagadador
            PayUParameters::IP_ADDRESS => "127.0.0.1",
            //Cookie de la sesión actual.
            PayUParameters::PAYER_COOKIE=>"pt1t38347bs6jc9ruv2ecpv7o2",
            //Cookie de la sesión actual.
            PayUParameters::USER_AGENT=>"Mozilla/5.0 (Windows NT 5.1; rv:18.0) Gecko/20100101 Firefox/18.0"*/
        );

        $response = PayUPayments::doAuthorizationAndCapture($parameters);

        if ($response) {
            /*$response->transactionResponse->orderId;
            $response->transactionResponse->transactionId;
            $response->transactionResponse->state;
            if ($response->transactionResponse->state=="PENDING") {
                $response->transactionResponse->pendingReason;
            }
            $response->transactionResponse->paymentNetworkResponseCode;
            $response->transactionResponse->paymentNetworkResponseErrorMessage;
            $response->transactionResponse->trazabilityCode;
            $response->transactionResponse->responseCode;
            $response->transactionResponse->responseMessage;*/
        }

        return $response;
    }

    /**
     * @param $payerId
     * @param $tokenId
     * @param string $startDate
     * @param string $endDate
     */
    public function lookToken($payerId, $tokenId, $startDate="2010-01-01T12:00:00", $endDate="2015-01-01T12:00:00")
    {
        $parameters = array(
            //Ingresa aquí el identificador del pagador.
            PayUParameters::PAYER_ID =>$payerId,
            //Ingresa aquí el identificador del token.
            PayUParameters::TOKEN_ID => $tokenId,
            //Ingresa aquí la fecha inicial desde donde filtrar con la fecha final hasta donde filtrar.
            PayUParameters::START_DATE=> $startDate,
            PayUParameters::END_DATE=> $endDate
        );

        $response=PayUTokens::find($parameters);

        if($response) {
            $credit_cards = $response->creditCardTokenList;
            foreach ($credit_cards as $credit_card) {
                $credit_card->creditCardTokenId;
                $credit_card->maskedNumber;
                $credit_card->payerId;
                $credit_card->identificationNumber;
                $credit_card->paymentMethod;
            }
        }
    }

    /**
     * @param $payerId
     * @param $tokenId
     * @return mixed
     */
    public function deleteToken($payerId, $tokenId)
    {
        $parameters = array(
            //Ingresa aquí el identificador del pagador.
            PayUParameters::PAYER_ID => $payerId,
            //Ingresa aquí el identificador del token.
            PayUParameters::TOKEN_ID => $tokenId
        );

        $response=PayUTokens::remove($parameters);

        if($response){

        }
        return $response;
    }

    /**
     * @param $description
     * @param $code
     * @param $interval
     * @param $intervalCount
     * @param string $currency
     * @param $value
     * @param $accountId
     * @param string $attempsDelay
     * @param $maxPayments
     * @param string $paymentsAttemps
     * @param string $maxPending
     * @param string $trialDays
     * @return mixed
     */
    public function createPlan($description, $code, $interval, $intervalCount, $currency='COP', $value, $accountId, $attempsDelay="1", $maxPayments, $paymentsAttemps="3", $maxPending="0", $trialDays="0")
    {
        $parameters = array(
            // Ingresa aquí la descripción del plan
            PayUParameters::PLAN_DESCRIPTION => $description,
            // Ingresa aquí el código de identificación para el plan
            PayUParameters::PLAN_CODE => $code,
            // Ingresa aquí el intervalo del plan
            //DAY||WEEK||MONTH||YEAR
            PayUParameters::PLAN_INTERVAL => $interval,
            // Ingresa aquí la cantidad de intervalos
            PayUParameters::PLAN_INTERVAL_COUNT => $intervalCount,
            // Ingresa aquí la moneda para el plan
            PayUParameters::PLAN_CURRENCY => $currency,
            // Ingresa aquí el valor del plan
            PayUParameters::PLAN_VALUE => $value,
            // Ingresa aquí la cuenta Id del plan
            PayUParameters::ACCOUNT_ID => $accountId,
            // Ingresa aquí el intervalo de reintentos
            PayUParameters::PLAN_ATTEMPTS_DELAY => $attempsDelay,
            // Ingresa aquí la cantidad de cobros que componen el plan
            PayUParameters::PLAN_MAX_PAYMENTS => $maxPayments,
            // Ingresa aquí la cantidad total de reintentos para cada pago rechazado de la suscripción
            PayUParameters::PLAN_MAX_PAYMENT_ATTEMPTS => $paymentsAttemps,
            // Ingresa aquí la cantidad máxima de pagos pendientes que puede tener una suscripción antes de ser cancelada.
            PayUParameters::PLAN_MAX_PENDING_PAYMENTS => $maxPending,
            // Ingresa aquí la cantidad de días de prueba de la suscripción.
            PayUParameters::TRIAL_DAYS => $trialDays,
        );

        $response = PayUSubscriptionPlans::create($parameters);
        if(isset($response['id'])){
            return $response['id'];
        }
        return $response;
    }

    /**
     * @param $description
     * @param $code
     * @param string $currency
     * @param $value
     * @param string $attempsDelay
     * @param string $maxPaymentsAtt
     * @param string $maxPending
     * @return mixed
     */
    public function updatePlan($description, $code,  $currency='COP', $value,$attempsDelay="1", $maxPaymentsAtt="3", $maxPending="0")
    {
        $parameters = array(
            // Ingresa aquí la descripción del plan
            PayUParameters::PLAN_DESCRIPTION => $description,
            // Ingresa aquí el código de identificación para el plan
            PayUParameters::PLAN_CODE => $code,
            // Ingresa aquí la moneda para el plan
            PayUParameters::PLAN_CURRENCY => $currency,
            // Ingresa aquí el valor del plan
            PayUParameters::PLAN_VALUE => $value,
            // Ingresa aquí el intervalo de reintentos
            PayUParameters::PLAN_ATTEMPTS_DELAY => $attempsDelay,
            // Ingresa aquí la cantidad total de reintentos para cada pago rechazado de la suscripción
            PayUParameters::PLAN_MAX_PAYMENT_ATTEMPTS => $maxPaymentsAtt,
            // Ingresa aquí la cantidad máxima de pagos pendientes que puede tener una suscripción antes de ser cancelada.
            PayUParameters::PLAN_MAX_PENDING_PAYMENTS => $maxPending,
        );
        $response = PayUSubscriptionPlans::update($parameters);

        if(isset($response['id'])){
            return $response['id'];
        }
        return $response;
    }

    /**
     * @param $codePlan
     * @return mixed
     */
    public function findPlan($codePlan)
    {
        $parameters = array(
            // Ingresa aquí el código de identificación para el plan
            PayUParameters::PLAN_CODE => $codePlan,
        );
        $response = PayUSubscriptionPlans::find($parameters);
/*
        if($response) {
            $response->id;
            $response->description;
            $response->accountId;
            $response->intervalCount;
            $response->interval;
            $response->maxPaymentsAllowed;
            $response->maxPaymentAttempts;
            $response->paymentAttemptsDelay;
            $response->maxPendingPayments;
            $response->trialDays;
        }*/
        return $response;
    }

    /**
     * @param $codePlan
     * @return mixed
     */
    public function deletePlan($codePlan)
    {
        $parameters = array(
            // Ingresa aquí el código de identificación para el plan
            PayUParameters::PLAN_CODE => $codePlan,
        );
        $response = PayUSubscriptionPlans::delete($parameters);
        if($response) {
        }
        return $response;
    }

    /**
     * Se obtiene el clienteId que posteriormente se asocia a una tarjeta
     *
     * @param $fullName
     * @param $email
     * @return mixed
     */
    public function createClient($fullName, $email)
    {
        $parameters = array(
            // Ingresa aquí el nombre del cliente
            PayUParameters::CUSTOMER_NAME => $fullName,
            // Ingresa aquí el correo del cliente
            PayUParameters::CUSTOMER_EMAIL => $email
        );

        $response = PayUCustomers::create($parameters);
        return $response;
    }

    public function updateClient($idClient, $fullname, $email)
    {
        $parameters = array(
            // Ingresa aquí el identificador del cliente,
            PayUParameters::CUSTOMER_ID => $idClient,
            // Ingresa aquí el nombre del cliente
            PayUParameters::CUSTOMER_NAME => $fullname,
            // Ingresa aquí el correo del cliente
            PayUParameters::CUSTOMER_EMAIL => $email
        );
        $response = PayUCustomers::update($parameters);

        if($response){
        }
        return $response;
    }

    /**
     * @param $clientId
     * @return mixed
     */
    public function findClient($clientId)
    {
        $parameters = array(
            // Ingresa aquí el identificador del cliente
            PayUParameters::CUSTOMER_ID => $clientId,
        );
        $response = PayUCustomers::find($parameters);

        if($response) {
            //dump($response);die;
        }
        return $response;
    }

    /**
     * @param $clientId
     * @return mixed
     */
    public function deleteClient($clientId)
    {
        $parameters = array(
            // Ingresa aquí el identificador del cliente,
            PayUParameters::CUSTOMER_ID => $clientId
        );

        $response = PayUCustomers::delete($parameters);

        if($response){

        }
        return $response;
    }

    /**
     * @param $clientId
     * @param $clientFullName
     * @param $creditCardNumber
     * @param $creditCardExpiration
     * @param $paymentMethod
     * @param $payerdni
     * @param $addressPayer
     * @param $cityPayer
     * @param $statePayer
     * @param string $countryPayer
     * @param $phonePayer
     * @return mixed
     */
    public function createCreditCard($clientId, $clientFullName, $creditCardNumber, $creditCardExpiration, $paymentMethod, $payerdni, $addressPayer, $cityPayer, $statePayer, $countryPayer='CO', $phonePayer)
    {
        $parameters = array(
            // Ingresa aquí el identificador del cliente,
            PayUParameters::CUSTOMER_ID => $clientId,
            // Ingresa aquí el nombre del cliente
            PayUParameters::PAYER_NAME => $clientFullName,
            // Ingresa aquí el número de la tarjeta de crédito
            PayUParameters::CREDIT_CARD_NUMBER => $creditCardNumber,
            // Ingresa aquí la fecha de expiración de la tarjeta de crédito en formato AAAA/MM
            PayUParameters::CREDIT_CARD_EXPIRATION_DATE => $creditCardExpiration,
            // Ingresa aquí el nombre de la franquicia de la tarjeta de crédito
            PayUParameters::PAYMENT_METHOD => $paymentMethod,
            // (OPCIONAL) Ingresa aquí el documento de identificación del pagador
            PayUParameters::PAYER_DNI => $payerdni,
            // (OPCIONAL) Ingresa aquí la primera línea de la dirección del pagador
            PayUParameters::PAYER_STREET => $addressPayer,
            // (OPCIONAL) Ingresa aquí la segunda línea de la dirección del pagador
            //PayUParameters::PAYER_STREET_2 => "17 25",
            // (OPCIONAL) Ingresa aquí la tercera línea de la dirección del pagador
            //PayUParameters::PAYER_STREET_3 => "Office 301",
            // (OPCIONAL) Ingresa aquí la ciudad de la dirección del pagador
            PayUParameters::PAYER_CITY => $cityPayer,
            // (OPCIONAL) Ingresa aquí el estado o departamento de la dirección del pagador
            PayUParameters::PAYER_STATE => $statePayer,
            // (OPCIONAL) Ingresa aquí el código del país de la dirección del pagador
            PayUParameters::PAYER_COUNTRY => $countryPayer,
            // (OPCIONAL) Ingresa aquí el código postal de la dirección del pagador
            //PayUParameters::PAYER_POSTAL_CODE => "00000",
            // (OPCIONAL) Ingresa aquí el número telefónico del pagador
            PayUParameters::PAYER_PHONE => $phonePayer
        );

        $response = PayUCreditCards::create($parameters);

        return $response;
    }

    /**
     * @param $tokenId
     * @param $payerFullName
     * @param $creditCardExpiration
     * @param $payerdni
     * @param $addressPayer
     * @param $cityPayer
     * @param $statePayer
     * @param string $countryPayer
     * @param $phonePayer
     * @return mixed
     */
    public function updateCreditCard($tokenId, $payerFullName, $creditCardExpiration, $payerdni, $addressPayer, $cityPayer, $statePayer, $countryPayer='CO', $phonePayer)
    {
        $parameters = array(
            // Ingresa aquí el identificador del token de la tarjeta.
            PayUParameters::TOKEN_ID => $tokenId,
            // Ingresa aquí el nombre del cliente
            PayUParameters::PAYER_NAME => $payerFullName,
            // Ingresa aquí la fecha de expiración de la tarjeta de crédito en formato AAAA/MM
            PayUParameters::CREDIT_CARD_EXPIRATION_DATE => $creditCardExpiration,
            // (OPCIONAL) Ingresa aquí el documento de identificación del pagador
            PayUParameters::PAYER_DNI => $payerdni,
            // (OPCIONAL) Ingresa aquí la primera línea de la dirección del pagador
            PayUParameters::PAYER_STREET => $addressPayer,
            // (OPCIONAL) Ingresa aquí la segunda línea de la dirección del pagador
            // PayUParameters::PAYER_STREET_2 => "17 25",
            // (OPCIONAL) Ingresa aquí la tercera línea de la dirección del pagador
            // PayUParameters::PAYER_STREET_3 => "Office 301",
            // (OPCIONAL) Ingresa aquí la ciudad de la dirección del pagador
            PayUParameters::PAYER_CITY => $cityPayer,
            // (OPCIONAL) Ingresa aquí el estado o departamento de la dirección del pagador
            PayUParameters::PAYER_STATE => $statePayer,
            // (OPCIONAL) Ingresa aquí el código del país de la dirección del pagador
            PayUParameters::PAYER_COUNTRY => $countryPayer,
            // (OPCIONAL) Ingresa aquí el código postal de la dirección del pagador
            //PayUParameters::PAYER_POSTAL_CODE => "00000",
            // (OPCIONAL) Ingresa aquí el número telefónico del pagador
            PayUParameters::PAYER_PHONE => $phonePayer
        );

        $response= PayUCreditCards::update($parameters);

        if($response){

        }
        return $response;
    }

    /**
     * @param $tokenId
     * @return mixed
     */
    public function findCreditCard($tokenId)
    {
        $parameters = array(
            // Ingresa aquí el identificador del token de la tarjeta.
            PayUParameters::TOKEN_ID => $tokenId
        );
        $response = PayUCreditCards::find($parameters);

        if($response){
            $response->token;
            $response->number;
            $response->type;
            $response->name;
            $address=$response->address;
            $address->line1;
            $address->line2;
            $address->line3;
            $address->city;
            $address->state;
            $address->country;
            $address->postalCode;
            $address->phone;
        }
        return $response;
    }

    /**
     * @param $tokenId
     * @param $clientId
     * @return mixed
     */
    public function deleteCreditCard($tokenId, $clientId)
    {
        $parameters = array(
            // Ingresa aquí el identificador del token de la tarjeta.
            PayUParameters::TOKEN_ID => $tokenId,
            // Ingresa aquí el identificador del cliente,
            PayUParameters::CUSTOMER_ID => $clientId
        );
        $response = PayUCreditCards::delete($parameters);

        if($response){

        }
        return $response;
    }

    /**
     * @param string $installmentsNumber Número de cuotas en las que se diferirá cada cobro de la suscripción.
     * @param string $trialDays Días de prueba que téndra la suscripción sin generar cobros.
     * @param string $clientFullName nombre del Cliente asociado a la suscripción.
     * @param string $clientEmail email del Cliente asociado a la suscripción.
     * @param string $payerFullName
     * @param string $creditCardNumber
     * @param string $creditCardExpiration
     * @param string $creditCardMethod
     * @param string $payerdni
     * @param string $addresPayer
     * @param string $cityPayer
     * @param string $statePayer
     * @param string $countryPayer
     * @param string $phonePayer
     * @param string $descriptionPlan
     * @param string $codePlan
     * @param string $periodicityPlan
     * @param string $intervalCount
     * @param string $currencyPlan
     * @param string $valuePlan
     * @param string $tax
     * @param string $taxreturnBase
     * @param string $accountId
     * @param string $attempDelay
     * @param string $countPayment
     * @param string $maxAttemps
     * @param string $pendingPay
     * @return mixed
     */
    public function createSubscriptionAllNew($installmentsNumber, $trialDays="0", $clientFullName, $clientEmail, $payerFullName, $creditCardNumber, $creditCardExpiration, $creditCardMethod="Visa", $payerdni, $addresPayer, $cityPayer, $statePayer, $countryPayer='CO', $phonePayer, $descriptionPlan, $codePlan, $periodicityPlan, $intervalCount, $currencyPlan='COP', $valuePlan, $tax, $taxreturnBase, $accountId, $attempDelay, $countPayment, $maxAttemps,$pendingPay)
    {
        $parameters = array(
            // Ingresa aquí el número de cuotas a pagar.
            PayUParameters::INSTALLMENTS_NUMBER => $installmentsNumber,
            // Ingresa aquí la cantidad de días de prueba
            PayUParameters::TRIAL_DAYS => $trialDays,

            // -- Parámetros del cliente --
            // Ingresa aquí el nombre del cliente
            PayUParameters::CUSTOMER_NAME => $clientFullName,
            // Ingresa aquí el email del cliente
            PayUParameters::CUSTOMER_EMAIL => $clientEmail,

            // -- Parámetros de la tarjeta de crédito --
            // Ingresa aquí el nombre del pagador.
            PayUParameters::PAYER_NAME => $payerFullName,
            // Ingresa aquí el número de la tarjeta de crédito
            PayUParameters::CREDIT_CARD_NUMBER => $creditCardNumber,
            // Ingresa aquí la fecha de expiración de la tarjeta de crédito en formato AAAA/MM
            PayUParameters::CREDIT_CARD_EXPIRATION_DATE => $creditCardExpiration,
            // Ingresa aquí el nombre de la franquicia de la tarjeta de crédito
            PayUParameters::PAYMENT_METHOD => $creditCardMethod,
            // (OPCIONAL) Ingresa aquí el documento de identificación del pagador
            PayUParameters::PAYER_DNI => $payerdni,
            // (OPCIONAL) Ingresa aquí la primera línea de la dirección del pagador
            PayUParameters::PAYER_STREET =>$addresPayer,
            // (OPCIONAL) Ingresa aquí la segunda línea de la dirección del pagador
            //PayUParameters::PAYER_STREET_2 => "17 25",
            // (OPCIONAL) Ingresa aquí la tercera línea de la dirección del pagador
            //PayUParameters::PAYER_STREET_3 => "Of 301",
            // (OPCIONAL) Ingresa aquí la ciudad de la dirección del pagador
            PayUParameters::PAYER_CITY => $cityPayer,
            // (OPCIONAL) Ingresa aquí el estado o departamento de la dirección del pagador
            PayUParameters::PAYER_STATE => $statePayer,
            // (OPCIONAL) Ingresa aquí el código del país de la dirección del pagador
            PayUParameters::PAYER_COUNTRY => $countryPayer,
            // (OPCIONAL) Ingresa aquí el código postal de la dirección del pagador
            //PayUParameters::PAYER_POSTAL_CODE => "00000",
            // (OPCIONAL) Ingresa aquí el número telefónico del pagador
            PayUParameters::PAYER_PHONE => $phonePayer,

            // -- Parámetros del plan --
            // Ingresa aquí la descripción del plan
            PayUParameters::PLAN_DESCRIPTION => $descriptionPlan,
            // Ingresa aquí el código de identificación para el plan
            PayUParameters::PLAN_CODE => $codePlan,
            // Ingresa aquí el intervalo del plan
            PayUParameters::PLAN_INTERVAL => $periodicityPlan,
            // Ingresa aquí la cantidad de intervalos
            PayUParameters::PLAN_INTERVAL_COUNT => $intervalCount,
            // Ingresa aquí la moneda para el plan
            PayUParameters::PLAN_CURRENCY => $currencyPlan,
            // Ingresa aquí el valor del plan
            PayUParameters::PLAN_VALUE => $valuePlan,
            //(OPCIONAL) Ingresa aquí el valor del impuesto
            PayUParameters::PLAN_TAX => $tax,
            //(OPCIONAL) Ingresa aquí la base de devolución sobre el impuesto
            PayUParameters::PLAN_TAX_RETURN_BASE => $taxreturnBase,
            // Ingresa aquí la cuenta Id del plan
            PayUParameters::ACCOUNT_ID => $accountId,
            // Ingresa aquí el intervalo de reintentos
            PayUParameters::PLAN_ATTEMPTS_DELAY => $attempDelay,
            // Ingresa aquí la cantidad de cobros que componen el plan
            PayUParameters::PLAN_MAX_PAYMENTS => $countPayment,
            // Ingresa aquí la cantidad total de reintentos para cada pago rechazado de la suscripción
            PayUParameters::PLAN_MAX_PAYMENT_ATTEMPTS => $maxAttemps,
            // Ingresa aquí la cantidad máxima de pagos pendientes que puede tener una suscripción antes de ser cancelada.
            PayUParameters::PLAN_MAX_PENDING_PAYMENTS => $pendingPay,
            // Ingresa aquí la cantidad de días de prueba de la suscripción.
            PayUParameters::TRIAL_DAYS => $trialDays,
        );

        $response = PayUSubscriptions::createSubscription($parameters);

        if($response){
           // dump($response);die;
        }
        return $response;
    }

    /**
     * @param $planCode
     * @param $clientId
     * @param $tokenId
     * @param string $maxPayments
     * @param $installmentsNumber
     * @return mixed
     */
    public function createSubscriptionAllExists($planCode, $clientId, $tokenId, $maxPayments="12", $installmentsNumber)
    {
        $parameters = array(
            // Ingresa aquí el código del plan a suscribirse.
            PayUParameters::PLAN_CODE => $planCode,
            // Ingresa aquí el identificador del pagador.
            PayUParameters::CUSTOMER_ID => $clientId,
            // Ingresa aquí el identificador del token de la tarjeta.
            PayUParameters::TOKEN_ID => $tokenId,
            // Ingresa aquí la cantidad de días de prueba de la suscripción.
            PayUParameters::PLAN_MAX_PAYMENTS =>$maxPayments,
            // Ingresa aquí el número de cuotas a pagar.
            PayUParameters::INSTALLMENTS_NUMBER => $installmentsNumber,
        );
        $response = PayUSubscriptions::createSubscription($parameters);
        return $response;
    }

    /**
     * @param $idSubscription
     * @param $codePlan
     * @return mixed
     */
    public function updateSubscriptionPlan($idSubscription, $codePlan)
    {
        $parameters = array(
            // Ingresa aquí el código del plan a suscribirse.
            PayUParameters::SUBSCRIPTION_ID => $idSubscription,
            // Ingresa aquí el identificador del pagador.
            PayUParameters::PLAN_CODE => $codePlan,

        );
        $response = PayUSubscriptions::update($parameters);

        if($response){
        }
        return $response;
    }

    /**
     * @param $idSubscription
     * @return mixed
     */
    public function findSubscriptionPlan($idSubscription)
    {
        $parameters = array(
            // Ingresa aquí el código del plan a suscribirse.
            PayUParameters::SUBSCRIPTION_ID => $idSubscription,

        );
        $response = PayUSubscriptions::find($parameters);

        return $response;
    }

    /**
     * @param $susbcriptionId
     * @return mixed
     */
    public function deleteSubscription($susbcriptionId)
    {
        $parameters = array(
            // Ingresa aquí el ID de la suscripción.
            PayUParameters::SUBSCRIPTION_ID => $susbcriptionId,
        );

        $response = PayUSubscriptions::cancel($parameters);

        if($response){
        }
        return $response;
    }

    /**
     * @param $description
     * @param $value
     * @param string $currency
     * @param $subscriptionId
     * @param string $tax
     * @param string $taxReturnBase
     * @return mixed
     */
    public function createExtraBill($description, $value, $currency='COP', $subscriptionId, $tax="0", $taxReturnBase="0")
    {
        $parameters = array(
            //Descripción del item
            PayUParameters::DESCRIPTION => $description,
            //Valor del item
            PayUParameters::ITEM_VALUE => $value,
            //Moneda
            PayUParameters::CURRENCY => $currency,
            //Identificador de la subscripción
            PayUParameters::SUBSCRIPTION_ID => $subscriptionId,
            //Impuestos - opcional
            PayUParameters::ITEM_TAX => $tax,
            //Base de devolución - opcional
            PayUParameters::ITEM_TAX_RETURN_BASE => $taxReturnBase,
        );

        $response = PayURecurringBillItem::create($parameters);

        if($response){
            $response->id;
        }
        return $response;
    }

    /**
     * @param $extraBillId
     * @param $description
     * @param $value
     * @param string $currency
     * @param $tax
     * @param $taxreturnBase
     * @return mixed
     */
    public function updateExtraBill($extraBillId, $description, $value, $currency='COP', $tax, $taxreturnBase)
    {
        $parameters = array(
            //Identificador del cargo extra
            PayUParameters::RECURRING_BILL_ITEM_ID => $extraBillId,
            //Descripción del item
            PayUParameters::DESCRIPTION => $description,
            //Valor del item
            PayUParameters::ITEM_VALUE => $value,
            //Moneda
            PayUParameters::CURRENCY => $currency,
            //Impuestos - opcional
            PayUParameters::ITEM_TAX => $tax,
            //Base de devolución - opcional
            PayUParameters::ITEM_TAX_RETURN_BASE => $taxreturnBase,
        );
        $response = PayURecurringBillItem::update($parameters);

        if($response){
        }
        return $response;
    }

    /**
     * @param $extraBillId
     * @return mixed
     */
    public function findExtraBill($extraBillId)
    {
        $parameters = array(
            //Identificador del cargo extra
            PayUParameters::RECURRING_BILL_ITEM_ID => $extraBillId,
        );

        $response = PayURecurringBillItem::find($parameters);

        if($response){
            $response->description;
            $response->subscriptionId;
            $response->recurringBillId;
        }
        return $response;
    }

    /**
     * @param $extraBillId
     * @return mixed
     */
    public function deleteExtraBill($extraBillId)
    {
        $parameters = array(
            //Identificador del cargo extra
            PayUParameters::RECURRING_BILL_ITEM_ID => $extraBillId,
        );

        $response = PayURecurringBillItem::delete($parameters);

        if($response){

        }
        return $extraBillId;
    }

    /**
     * @param $accoundId
     * @param $referenceCode
     * @param $description
     * @param $value
     * @param string $currency
     * @param $payerName
     * @param $payerEmail
     * @param $payerPhone
     * @param $payerdni
     * @param $payerCity
     * @param $payerState
     * @param $payerCountry
     * @param $payerAddress
     * @param $creditCardNumber
     * @param $creditCardExpiration
     * @param $creditCardSecurity
     * @param string $paymentMethod
     * @param $installmentsNumber
     * @return mixed
     */
    public function testBill($accoundId, $referenceCode, $description, $value, $currency='COP', $payerName, $payerEmail, $payerPhone, $payerdni, $payerCity, $payerState, $payerCountry, $payerAddress, $creditCardNumber, $creditCardExpiration, $creditCardSecurity,$paymentMethod='VISA', $installmentsNumber)
    {
        switch ($paymentMethod){
            case 'VISA':
                $metodo= PaymentMethods::VISA;
                break;
            case 'MASTERCARD':
                $metodo= PaymentMethods::MASTERCARD;
                break;
            case 'AMEX':
                $metodo= PaymentMethods::AMEX;
                break;
            case 'DINERS':
                $metodo= PaymentMethods::DINERS;
                break;
            default:
                $metodo= PaymentMethods::DINERS;
                break;
        }

        $parameters = array(
            //Ingrese aquí el identificador de la cuenta.
            PayUParameters::ACCOUNT_ID => $accoundId,
            //Ingrese aquí el código de referencia.
            PayUParameters::REFERENCE_CODE => $referenceCode,
            //Ingrese aquí la descripción.
            PayUParameters::DESCRIPTION => $description,

            // -- Valores --
            //Ingrese aquí el valor.
            PayUParameters::VALUE => $value,
            //Ingrese aquí la moneda.
            PayUParameters::CURRENCY => $currency,

            /* -- Comprador
            //Ingrese aquí el nombre del comprador.
            PayUParameters::BUYER_NAME => "First name and second buyer  name",
            //Ingrese aquí el email del comprador.
            PayUParameters::BUYER_EMAIL => "buyer_test@test.com",
            //Ingrese aquí el teléfono de contacto del comprador.
            PayUParameters::BUYER_CONTACT_PHONE => "7563126",
            //Ingrese aquí el documento de contacto del comprador.
            PayUParameters::BUYER_DNI => "5415668464654",
            //Ingrese aquí la dirección del comprador.
            PayUParameters::BUYER_STREET => "calle 100",
            PayUParameters::BUYER_STREET_2 => "5555487",
            PayUParameters::BUYER_CITY => "Medellin",
            PayUParameters::BUYER_STATE => "Antioquia",
            PayUParameters::BUYER_COUNTRY => "CO",
            PayUParameters::BUYER_POSTAL_CODE => "000000",
            PayUParameters::BUYER_PHONE => "7563126", */

            // -- pagador --
            //Ingrese aquí el nombre del pagador.
            PayUParameters::PAYER_NAME => $payerName,
            //Ingrese aquí el email del pagador.
            PayUParameters::PAYER_EMAIL => $payerEmail,
            //Ingrese aquí el teléfono de contacto del pagador.
            PayUParameters::PAYER_CONTACT_PHONE => $payerPhone,
            //Ingrese aquí el documento de contacto del pagador.
            PayUParameters::PAYER_DNI => $payerdni,
            //Ingrese aquí la dirección del pagador.
            PayUParameters::PAYER_STREET => $payerAddress,
            //PayUParameters::PAYER_STREET_2 => "125544",
            PayUParameters::PAYER_CITY => $payerCity,
            PayUParameters::PAYER_STATE => $payerState,
            PayUParameters::PAYER_COUNTRY => $payerCountry,
            //PayUParameters::PAYER_POSTAL_CODE => "000000",
            PayUParameters::PAYER_PHONE => $payerPhone,

            // -- Datos de la tarjeta de crédito --
            //Ingrese aquí el número de la tarjeta de crédito
            PayUParameters::CREDIT_CARD_NUMBER => $creditCardNumber,
            //Ingrese aquí la fecha de vencimiento de la tarjeta de crédito
            PayUParameters::CREDIT_CARD_EXPIRATION_DATE => $creditCardExpiration,
            //Ingrese aquí el código de seguridad de la tarjeta de crédito
            PayUParameters::CREDIT_CARD_SECURITY_CODE=> $creditCardSecurity,
            //Ingrese aquí el nombre de la tarjeta de crédito
            //PaymentMethods::VISA||PaymentMethods::MASTERCARD||PaymentMethods::AMEX||PaymentMethods::DINERS
            PayUParameters::PAYMENT_METHOD => $metodo,

            //Ingrese aquí el número de cuotas.
            PayUParameters::INSTALLMENTS_NUMBER => $installmentsNumber,
            //Ingrese aquí el nombre del pais.
            PayUParameters::COUNTRY => PayUCountries::CO,

            /*Session id del device.
            PayUParameters::DEVICE_SESSION_ID => "vghs6tvkcle931686k1900o6e1",
            //IP del pagadador
            PayUParameters::IP_ADDRESS => "127.0.0.1",
            //Cookie de la sesión actual.
            PayUParameters::PAYER_COOKIE=>"pt1t38347bs6jc9ruv2ecpv7o2",
            //Cookie de la sesión actual.
            PayUParameters::USER_AGENT=>"Mozilla/5.0 (Windows NT 5.1; rv:18.0) Gecko/20100101 Firefox/18.0" */
        );

        //solicitud de autorización y captura
        $response = PayUPayments::doAuthorizationAndCapture($parameters);

        //  -- podrás obtener las propiedades de la respuesta --
        if($response){
           /* $response->transactionResponse->orderId;
            $response->transactionResponse->transactionId;
            $response->transactionResponse->state;
            if($response->transactionResponse->state=="PENDING"){
                $response->transactionResponse->pendingReason;
            }
            $response->transactionResponse->paymentNetworkResponseCode;
            $response->transactionResponse->paymentNetworkResponseErrorMessage;
            $response->transactionResponse->trazabilityCode;
            $response->transactionResponse->responseCode;
            $response->transactionResponse->responseMessage; */
        }
        return $response;
    }
}