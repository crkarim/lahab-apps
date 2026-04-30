<?php

namespace App\CentralLogics;

use Illuminate\Support\Facades\DB;
use Twilio\Rest\Client;

class SMSModule
{
    /**
     * @param string $receiver E.164 phone number.
     * @param string $message  When $raw is false, this is treated as the OTP
     *                         and substituted into the gateway's configured
     *                         `otp_template`. When $raw is true, the
     *                         message ships as-is — used for receipt SMS
     *                         and other non-OTP flows where we want the
     *                         link / body unmangled.
     * @param bool   $raw      Bypass the OTP template substitution. Some
     *                         template-id-based gateways (msg91, 2factor)
     *                         can't honour this and will degrade to OTP
     *                         mode — for those, switch to a free-text
     *                         gateway (twilio, nexmo, alphanet_sms,
     *                         signal_wire) if you need link delivery.
     */
    public static function send($receiver, $message, $raw = false): string
    {
        $config = self::get_settings('twilio');
        if (isset($config) && $config['status'] == 1) {
            return self::twilio($receiver, $message, $raw);
        }

        $config = self::get_settings('nexmo');
        if (isset($config) && $config['status'] == 1) {
            return self::nexmo($receiver, $message, $raw);
        }

        $config = self::get_settings('2factor');
        if (isset($config) && $config['status'] == 1) {
            return self::two_factor($receiver, $message);
        }

        $config = self::get_settings('msg91');
        if (isset($config) && $config['status'] == 1) {
            return self::msg_91($receiver, $message);
        }

        $config = self::get_settings('signal_wire');
        if (isset($config) && $config['status'] == 1) {
            return self::signal_wire($receiver, $message, $raw);
        }

        $config = self::get_settings('alphanet_sms');
        if (isset($config) && $config['status'] == 1) {
            return self::alphanet_sms($receiver, $message, $raw);
        }

        return 'not_found';
    }

    public static function twilio($receiver, $otp, $raw = false): string
    {
        $config = self::get_settings('twilio');
        $response = 'error';
        if (isset($config) && $config['status'] == 1) {
            // Receipt SMS / other free-text messages bypass the OTP
            // template so the link survives intact. OTP path keeps the
            // legacy substitution for backwards-compat with existing
            // admin-configured templates.
            $message = $raw ? (string) $otp : self::applyOtpTemplate($config['otp_template'], $otp);
            $sid = $config['sid'];
            $token = $config['token'];
            try {
                $twilio = new Client($sid, $token);
                $twilio->messages
                    ->create($receiver, // to
                        array(
                            "messagingServiceSid" => $config['messaging_service_sid'],
                            "body" => $message
                        )
                    );
                $response = 'success';
            } catch (\Exception $exception) {
                $response = 'error';
            }
        }
        return $response;
    }

    public static function nexmo($receiver, $otp, $raw = false): string
    {
        $config = self::get_settings('nexmo');
        $response = 'error';
        if (isset($config) && $config['status'] == 1) {
            $message = $raw ? (string) $otp : self::applyOtpTemplate($config['otp_template'], $otp);
            try {
                $ch = curl_init();

                curl_setopt($ch, CURLOPT_URL, 'https://rest.nexmo.com/sms/json');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, "from=".$config['from']."&text=".$message."&to=".$receiver."&api_key=".$config['api_key']."&api_secret=".$config['api_secret']);

                $headers = array();
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

                $result = curl_exec($ch);
                if (curl_errno($ch)) {
                    echo 'Error:' . curl_error($ch);
                }
                curl_close($ch);
                $response = 'success';
            } catch (\Exception $exception) {
                $response = 'error';
            }
        }
        return $response;
    }

    public static function two_factor($receiver, $otp): string
    {

        $config = self::get_settings('2factor');
        $response = 'error';
        if (isset($config) && $config['status'] == 1) {
            $apiKey = $config['api_key'];
            $otpTemplate = $config['otp_template'] ?? '';
            $apiUrl = "https://2factor.in/API/V1/$apiKey/SMS/$receiver/$otp/$otpTemplate";

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
            ));
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if (!$err) {
                $response = 'success';
            } else {
                $response = 'error';
            }
        }
        return $response;
    }

    public static function msg_91($receiver, $otp): string
    {
        $config = self::get_settings('msg91');
        $response = 'error';
        if (isset($config) && $config['status'] == 1) {
            $receiver = str_replace("+", "", $receiver);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.msg91.com/api/v5/otp?template_id=" . $config['template_id'] . "&mobile=" . $receiver . "&authkey=" . $config['auth_key'] . "",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_POSTFIELDS => "{\"OTP\":\"$otp\"}",
                CURLOPT_HTTPHEADER => array(
                    "content-type: application/json"
                ),
            ));
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if (!$err) {
                $response = 'success';
            } else {
                $response = 'error';
            }
        }
        return $response;
    }

    public static function signal_wire($receiver, $otp, $raw = false): string
    {
        $config = self::get_settings('signal_wire');
        $response = 'error';
        if (isset($config) && $config['status'] == 1) {

            $message = $raw ? (string) $otp : str_replace("#OTP#", $otp, "Your otp is #OTP#.");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://" . $config['space_url'] . "/api/laml/2010-04-01/Accounts/" . $config['project_id'] . "/Messages");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $config['project_id'] . ':' . $config['token']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "From=" . $config['from'] . "&To=" . $receiver . "&Body=" . $message);

            $response = curl_exec($ch);
            $error = curl_error($ch);

            curl_close($ch);

            if (!$error) {
                $response = 'success';
            } else {
                $response = 'error';
            }

        }
        return $response;
    }

    public static function alphanet_sms($receiver, $otp, $raw = false): string
    {
        $config = self::get_settings('alphanet_sms');
        $response = 'error';
        if (isset($config) && $config['status'] == 1) {
            $receiver = str_replace("+", "", $receiver);
            // Free-text BD provider — receipts pass through unchanged
            // when $raw is true, so the receipt link stays clickable.
            $message = $raw ? (string) $otp : self::applyOtpTemplate($config['otp_template'], $otp);
            $api_key = $config['api_key'];

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.sms.net.bd/sendsms',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array('api_key' => $api_key, 'msg' => $message, 'to' => $receiver),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if (!$err) {
                $response = 'success';
            } else {
                $response = 'error';
            }
        }
        return $response;
    }

    public static function get_settings($name)
    {
        $config = DB::table('addon_settings')->where('key_name', $name)
            ->where('settings_type', 'sms_config')->first();

        if (isset($config) && !is_null($config->live_values)) {
            return json_decode($config->live_values, true);
        }
        return null;
    }

    /**
     * Apply the configured `otp_template` while tolerating different
     * placeholder formats — `#OTP#`, `{OTP}`, `{{OTP}}`, `%OTP%`, lower
     * or upper case. If none is present in the template, append the
     * OTP at the end as a last resort so the user still receives the
     * code instead of a placeholder string.
     *
     * Public so the trait `App\Traits\SmsGateway` can reuse the same
     * resolver — we want OTP and receipt SMS behaviour aligned across
     * both stacks.
     */
    public static function applyOtpTemplate(?string $template, string $otp): string
    {
        if ($template === null || $template === '') return $otp;
        // Longest patterns first — `{{OTP}}` must beat `{OTP}` so the
        // double-brace form doesn't leave a stray brace pair behind.
        $patterns = ['{{OTP}}', '{{otp}}', '#OTP#', '#otp#', '{OTP}', '{otp}', '%OTP%', '%otp%'];
        foreach ($patterns as $p) {
            if (strpos($template, $p) !== false) {
                return str_replace($p, $otp, $template);
            }
        }
        // No known placeholder — append the OTP so the message at least
        // contains the code (better than a mute "Your OTP is" template).
        return rtrim($template) . ' ' . $otp;
    }
}
