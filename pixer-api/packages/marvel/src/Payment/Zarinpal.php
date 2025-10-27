<?php

namespace Marvel\Payments;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Marvel\Payments\PaymentInterface;
use Marvel\Enums\OrderStatus;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\PaymentIntent;
use Marvel\Enums\PaymentStatus;
use Marvel\Traits\PaymentTrait;
use Marvel\Payments\Base;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Zarinpal extends Base implements PaymentInterface
{
    use PaymentTrait;

    protected string $merchant_id;
    protected bool $sandbox;
    protected bool $use_toman;
    protected string $callback_url;
    protected string $api_base_url;
    protected string $payment_base_url;

    public function __construct()
    {
        parent::__construct();
        $this->merchant_id = config('shop.zarinpal.merchant_id');
        $this->sandbox = config('shop.zarinpal.sandbox', true);
        $this->use_toman = config('shop.zarinpal.use_toman', false);
        $this->callback_url = config('shop.zarinpal.callback_url');
        
        // Set API URLs based on sandbox mode
        if ($this->sandbox) {
            $this->api_base_url = 'https://sandbox.zarinpal.com/pg/v4/payment';
            $this->payment_base_url = 'https://sandbox.zarinpal.com/pg/StartPay';
        } else {
            $this->api_base_url = 'https://payment.zarinpal.com/pg/v4/payment';
            $this->payment_base_url = 'https://payment.zarinpal.com/pg/StartPay';
        }
    }

    /**
     * Get payment intent for ZarinPal
     *
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function getIntent($data): array
    {
        try {
            extract($data);
            
            // Convert amount based on currency
            // ZarinPal works with Rial (IRR)
            // If use_toman is true, multiply by 10 to convert Toman to Rial
            $finalAmount = round($amount, 2);
            if ($this->use_toman) {
                $finalAmount = $finalAmount * 10; // Convert Toman to Rial
            }

            // Prepare request data
            $requestData = [
                'merchant_id' => $this->merchant_id,
                'amount' => (int) $finalAmount,
                'currency' => 'IRR', // ZarinPal only supports IRR
                'description' => config('shop.zarinpal.description', 'پرداخت سفارش') . ' #' . $order_tracking_number,
                'callback_url' => $this->callback_url,
                'metadata' => [
                    'mobile' => $user_phone ?? '',
                    'email' => $user_email ?? '',
                    'order_id' => $order_tracking_number,
                ],
            ];

            // Send request to ZarinPal
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->api_base_url . '/request.json', $requestData);

            $result = $response->json();

            // Check for errors
            if (!$response->successful() || !isset($result['data']['authority'])) {
                $errorCode = $result['errors']['code'] ?? 'unknown';
                $errorMessage = $this->getErrorMessage($errorCode);
                
                Log::error('ZarinPal Payment Request Failed', [
                    'order' => $order_tracking_number,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'response' => $result,
                ]);

                throw new HttpException(400, 'خطا در ایجاد درخواست پرداخت: ' . $errorMessage);
            }

            $authority = $result['data']['authority'];
            $redirectUrl = $this->payment_base_url . '/' . $authority;

            Log::info('ZarinPal Payment Intent Created', [
                'order' => $order_tracking_number,
                'authority' => $authority,
                'amount' => $finalAmount,
            ]);

            return [
                'order_tracking_number' => $order_tracking_number,
                'is_redirect' => true,
                'payment_id' => $authority,
                'redirect_url' => $redirectUrl,
                'currency' => 'IRR',
                'amount' => $finalAmount,
            ];

        } catch (Exception $e) {
            Log::error('ZarinPal Payment Intent Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new HttpException(400, 'خطا در اتصال به درگاه زرین‌پال');
        }
    }

    /**
     * Verify ZarinPal payment
     *
     * @param string $authority
     * @return mixed
     * @throws Exception
     */
    public function verify($authority): mixed
    {
        try {
            // Get payment intent by authority
            $paymentIntent = PaymentIntent::whereJsonContains('payment_intent_info', ['payment_id' => $authority])->first();

            if (!$paymentIntent) {
                throw new HttpException(400, 'تراکنش یافت نشد');
            }

            // Get order by tracking number
            $order = Order::where('tracking_number', $paymentIntent->tracking_number)->first();

            if (!$order) {
                throw new HttpException(400, 'سفارش یافت نشد');
            }

            // Get amount from order
            $amount = $order->paid_total;
            if ($this->use_toman) {
                $amount = $amount * 10; // Convert to Rial
            }

            // Prepare verification request
            $requestData = [
                'merchant_id' => $this->merchant_id,
                'amount' => (int) $amount,
                'authority' => $authority,
            ];

            // Send verification request
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($this->api_base_url . '/verify.json', $requestData);

            $result = $response->json();

            // Check verification result
            if ($response->successful() && isset($result['data']['code']) && $result['data']['code'] == 100) {
                Log::info('ZarinPal Payment Verified', [
                    'authority' => $authority,
                    'ref_id' => $result['data']['ref_id'],
                    'card_pan' => $result['data']['card_pan'] ?? 'N/A',
                ]);

                return [
                    'success' => true,
                    'ref_id' => $result['data']['ref_id'],
                    'card_pan' => $result['data']['card_pan'] ?? null,
                    'fee' => $result['data']['fee'] ?? 0,
                ];
            }

            // Payment verification failed
            $errorCode = $result['data']['code'] ?? $result['errors']['code'] ?? 'unknown';
            $errorMessage = $this->getErrorMessage($errorCode);

            Log::warning('ZarinPal Payment Verification Failed', [
                'authority' => $authority,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ];

        } catch (Exception $e) {
            Log::error('ZarinPal Verification Exception', [
                'authority' => $authority,
                'message' => $e->getMessage(),
            ]);
            throw new HttpException(400, 'خطا در تأیید پرداخت');
        }
    }

    /**
     * Handle ZarinPal webhooks/callbacks
     *
     * @param object $request
     * @return void
     */
    public function handleWebHooks($request): void
    {
        $authority = $request->input('Authority');
        $status = $request->input('Status');

        Log::info('ZarinPal Callback Received', [
            'authority' => $authority,
            'status' => $status,
        ]);

        // If payment was cancelled by user
        if ($status !== 'OK') {
            $this->updatePaymentOrderStatus($authority, OrderStatus::PENDING, PaymentStatus::FAILED);
            return;
        }

        // Verify the payment
        $verificationResult = $this->verify($authority);

        if ($verificationResult['success']) {
            $this->updatePaymentOrderStatus($authority, OrderStatus::PROCESSING, PaymentStatus::SUCCESS, $verificationResult);
        } else {
            $this->updatePaymentOrderStatus($authority, OrderStatus::PENDING, PaymentStatus::FAILED);
        }
    }

    /**
     * Update Payment and Order Status
     *
     * @param string $authority
     * @param string $orderStatus
     * @param string $paymentStatus
     * @param array|null $verificationData
     * @return void
     */
    public function updatePaymentOrderStatus($authority, $orderStatus, $paymentStatus, $verificationData = null): void
    {
        try {
            // Get payment intent by authority
            $paymentIntent = PaymentIntent::whereJsonContains('payment_intent_info', ['payment_id' => $authority])->first();

            if (!$paymentIntent) {
                Log::error('ZarinPal: PaymentIntent not found for authority', ['authority' => $authority]);
                return;
            }

            // Get order by tracking number
            $order = Order::where('tracking_number', $paymentIntent->tracking_number)->first();

            if (!$order) {
                Log::error('ZarinPal: Order not found for tracking number', [
                    'authority' => $authority,
                    'tracking_number' => $paymentIntent->tracking_number
                ]);
                return;
            }

            // Store verification data if successful
            if ($verificationData && isset($verificationData['ref_id'])) {
                $order->payment_intent->update([
                    'payment_intent_info' => array_merge(
                        $order->payment_intent->payment_intent_info ?? [],
                        [
                            'zarinpal_ref_id' => $verificationData['ref_id'],
                            'zarinpal_card_pan' => $verificationData['card_pan'] ?? null,
                            'zarinpal_fee' => $verificationData['fee'] ?? 0,
                        ]
                    ),
                ]);
            }

            $this->webhookSuccessResponse($order, $orderStatus, $paymentStatus);

        } catch (Exception $e) {
            Log::error('ZarinPal: Error updating order status', [
                'authority' => $authority,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get error message based on ZarinPal error code
     *
     * @param int|string $code
     * @return string
     */
    protected function getErrorMessage($code): string
    {
        $errors = [
            -9 => 'خطای اعتبارسنجی',
            -10 => 'آی‌پی یا مرچنت کد پذیرنده صحیح نیست',
            -11 => 'مرچنت کد فعال نیست. لطفا با تیم پشتیبانی زرین‌پال تماس بگیرید',
            -12 => 'تلاش بیش از حد در یک بازه زمانی کوتاه',
            -15 => 'ترمینال شما به حالت تعلیق در آمده است',
            -16 => 'سطح تأیید پذیرنده پایین‌تر از سطح نقره‌ای است',
            -17 => 'محدودیت پذیرنده در انجام تراکنش',
            -30 => 'اجازه دسترسی به متد مربوطه وجود ندارد',
            -31 => 'حساب بانکی تایید نشده است',
            -32 => 'مبلغ کمتر از حداقل مجاز است',
            -33 => 'مبلغ بیشتر از حداکثر مجاز است',
            -34 => 'مبلغ از سقف روزانه پذیرنده بیشتر است',
            -35 => 'مبلغ از سقف ماهانه پذیرنده بیشتر است',
            -40 => 'پارامترهای اضافی نامعتبر است',
            -41 => 'حداقل مبلغ پرداختی 1,000 ریال است',
            -50 => 'مبلغ پرداخت شده با مبلغ درخواستی مطابقت ندارد',
            -51 => 'پرداخت ناموفق بوده است',
            -52 => 'خطای غیرمنتظره. با پشتیبانی زرین‌پال تماس بگیرید',
            -53 => 'اتوریتی برای این مرچنت کد نیست',
            -54 => 'اتوریتی نامعتبر است',
            100 => 'تراکنش با موفقیت انجام شد',
            101 => 'تراکنش قبلاً تأیید شده است',
        ];

        return $errors[$code] ?? 'خطای نامشخص در درگاه پرداخت (کد: ' . $code . ')';
    }

    // ========== Required Interface Methods (Not Used for ZarinPal) ==========

    /**
     * createCustomer - Not used for ZarinPal
     */
    public function createCustomer($request): array
    {
        return [];
    }

    /**
     * attachPaymentMethodToCustomer - Not used for ZarinPal
     */
    public function attachPaymentMethodToCustomer(string $retrieved_payment_method, object $request): object
    {
        return (object) [];
    }

    /**
     * detachPaymentMethodToCustomer - Not used for ZarinPal
     */
    public function detachPaymentMethodToCustomer(string $retrieved_payment_method): object
    {
        return (object) [];
    }

    /**
     * retrievePaymentIntent - Not used for ZarinPal
     */
    public function retrievePaymentIntent($payment_intent_id): object
    {
        return (object) [];
    }

    /**
     * confirmPaymentIntent - Not used for ZarinPal
     */
    public function confirmPaymentIntent(string $payment_intent_id, array $data): object
    {
        return (object) [];
    }

    /**
     * setIntent - Not used for ZarinPal
     */
    public function setIntent(array $data): array
    {
        return [];
    }

    /**
     * retrievePaymentMethod - Not used for ZarinPal
     */
    public function retrievePaymentMethod(string $method_key): object
    {
        return (object) [];
    }
}
