<?php

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 * InvoicePlane
 *
 * @author		InvoicePlane Developers & Contributors
 * @copyright	Copyright (c) 2012 - 2018 InvoicePlane.com
 * @license		https://invoiceplane.com/license.txt
 * @link		https://invoiceplane.com
 */
use Jumbojett\OpenIDConnectClient;

#[AllowDynamicProperties]
class Sessions extends Base_Controller
{
    public function index()
    {
        redirect('sessions/login');
    }

    public function login()
    {
        $view_data = [
            'login_logo' => get_setting('login_logo'),
        ];

        if ($this->input->post('btn_login')) {
            $this->db->where('user_email', $this->input->post('email'));
            $query = $this->db->get('ip_users');
            $user  = $query->row();

            // Check if the user exists
            if (empty($user)) {
                $this->session->set_flashdata('alert_error', trans('loginalert_user_not_found'));
                redirect('sessions/login');
            } elseif ($user->user_active == 0) {
                // Check if the user is marked as active (not implemented: Todo?)
                $this->session->set_flashdata('alert_error', trans('loginalert_user_inactive'));
                redirect('sessions/login');
            } elseif ($this->authenticate($this->input->post('email'), $this->input->post('password'))) {
                if ($this->session->userdata('user_type') == 1) {
                    redirect('dashboard');
                } elseif ($this->session->userdata('user_type') == 2) {
                    redirect('guest');
                }
            } else {
                $this->session->set_flashdata('alert_error', trans('loginalert_credentials_incorrect'));
                redirect('sessions/login');
            }
        }

        if (($this->input->post('btn_openid')) || (isset($_REQUEST['code']) && isset($_REQUEST['state']))) {
            // openid login
            $openid_provider_url = $_ENV['OPENID_PROVIDER_URL'];
            $openid_client_id = $_ENV['OPENID_CLIENT_ID'];
            $openid_client_secret = $_ENV['OPENID_CLIENT_SECRET'];
            if ($openid_provider_url == null || $openid_provider_url == ''
                || $openid_client_id == null || $openid_client_id == ''
                || $openid_client_secret == null || $openid_client_secret == '') {
                $this->session->set_flashdata('alert_error', 'No OpenID identity provider configured.');
            } else {
                $oidc = new OpenIDConnectClient($openid_provider_url,
                                    $openid_client_id,
                                    $openid_client_secret);
                $oidc->setRedirectURL($_ENV['IP_URL'] . 'index.php/sessions/login');
                $redirect_url=$oidc->getRedirectURL();
                error_log("OpenID redirect URL = $redirect_url");
                $oidc->setHttpUpgradeInsecureRequests(false);
                $oidc->authenticate();
                $openid_user = $oidc->requestUserInfo();
                if ($this->authenticate_openid_user($openid_user)) {
                    if ($this->session->userdata('user_type') == 1) {
                        redirect('dashboard');
                    } elseif ($this->session->userdata('user_type') == 2) {
                        redirect('guest');
                    }
                }
            }
        }

        $this->load->view('session_login', $view_data);
    }

    /**
     * Authenticate an OpenID user.
     * @param mixed $openid_user
     * @return void
     */
    public function authenticate_openid_user($openid_user): bool
    {
        $user_email = $openid_user->email;
        $user_name = $openid_user->name;
        //check if user is banned
        $login_log = $this->_login_log_check($user_email);
        $this->db->where('user_email', $user_email);
        $query = $this->db->get('ip_users');
        if ($query->num_rows()) {
            $user = $query->row();
        } else {
            // user does not exist yet
            // since invoiceplane creates the invoices using the current user, we have
            // to use a workaround to create the additional user with the company name,
            // address etc. using the user with user_id 1 to create the invoices a different
            // user creates so that the new invoices have the correct company name etc.
            $this->db->where('user_id', 1);
            $query = $this->db->get('ip_users');
            $user1 = $query->row();
            $user = [
                'user_type' => 1, // admin
                'user_active' => 1, // active
                'user_name' => $user_name,
                'user_email' => $user_email,
                'user_language' => 'system',
                'user_password' => 'openid',
                'user_company' => $user1->user_company,
                'user_address_1' => $user1->user_address_1,
                'user_address_2' => $user1->user_address_2,
                'user_city' => $user1->user_city,
                'user_state' => $user1->user_state,
                'user_zip' => $user1->user_zip,
                'user_country' => $user1->user_country,
                'user_invoicing_contact' => $user1->user_invoicing_contact,
                'user_phone' => $user1->user_phone,
                'user_fax' => $user1->user_fax,
                'user_mobile' => $user1->user_mobile,
                'user_web' => $user1->user_web,
                'user_vat_id' => $user1->user_vat_id,
                'user_tax_code' => $user1->user_tax_code,
                'user_all_clients' => 0,
                'user_subscribernumber' => $user1->user_subscribernumber,
                'user_bank' => $user1->user_bank,
                'user_iban' => $user1->user_iban,
                'user_bic' => $user1->user_bic,
                'user_remittance_text' => $user1->user_remittance_text,
                'user_gln' => $user1->user_gln,
                'user_rcc' => $user1->user_rcc,
            ];
            $this->db->insert('ip_users', $user);
            $id = $this->db->insert_id();
            //now retrieve the user from the database to be sure
            $this->db->where('user_email', $user_email);
            $query = $this->db->get('ip_users');
            $user = $query->row();
        }
        $datetime = date('Y-m-d H:i:s');
        $session_data = [
            'user_type'     => $user->user_type,
            'user_id'       => $user->user_id,
            'user_name'     => $user->user_name,
            'user_email'    => $user->user_email,
            'user_company'  => $user->user_company,
            'user_language' => $user->user_language ?? 'system',
            'user_date_created' => $datetime,
            'user_date_modified' => $datetime,
        ];
        $this->session->set_userdata($session_data);
        $this->_login_log_reset($user_email);
        return true;
    }

    /**
     * @param $email_address
     * @param $password
     */
    public function authenticate($email_address, $password): bool
    {
        $this->load->model('mdl_sessions');
        //check if user is banned
        $login_log = $this->_login_log_check($email_address);
        if (empty($login_log) || $login_log->log_count < 10) {
            if ($this->mdl_sessions->auth($email_address, $password)) {
                $this->_login_log_reset($email_address);

                return true;
            }

            //track failed attempt
            $this->_login_log_addfailure($email_address);
        }

        return false;
    }

    public function logout()
    {
        $this->session->sess_destroy();

        redirect('sessions/login');
    }

    /**
     * @return mixed
     */
    public function passwordreset($token = null)
    {
        // Check if a token was provided
        if ($token) {
            if (preg_match("/[^[:alnum:]\-_]/", $token)) {
                log_message('error', 'Incoming token is not alphanumeric ' . $token);
                redirect('/');
            }

            //prevent brute force attacks by counting times a token is used
            $login_log_check = $this->_login_log_check($token);
            if ( ! empty($login_log_check) && $login_log_check->log_count > 10) {
                redirect($this->_get_safe_referer());
            } else {
                //the use of a token counts as a failure
                $this->_login_log_addfailure($token);
            }

            $this->db->where('user_passwordreset_token', $token);
            $user = $this->db->get('ip_users');
            $user = $user->row();

            if (empty($user)) {
                // Redirect back to the login screen with an alert
                $this->session->set_flashdata('alert_error', trans('wrong_passwordreset_token'));
                redirect('sessions/passwordreset');
            } else {
                //if token is valid, delete the failure attempt from
                //the login_log table
                $this->_login_log_reset($token);
            }

            $formdata = [
                'token'   => $token,
                'user_id' => $user->user_id,
            ];

            return $this->load->view('session_new_password', $formdata);
        }

        // Check if the form for a new password was used
        if ($this->input->post('btn_new_password')) {
            $new_password = $this->input->post('new_password', true);
            $user_id      = $this->input->post('user_id', true);

            if (empty($user_id) || empty($new_password)) {
                $this->session->set_flashdata('alert_error', trans('loginalert_no_password'));
                redirect($this->_get_safe_referer());
            }

            $this->load->model('users/mdl_users');

            // Check for the reset token
            $user = $this->mdl_users->get_by_id($user_id);

            if (empty($user)) {
                $this->session->set_flashdata('alert_error', trans('loginalert_user_not_found'));
                redirect($this->_get_safe_referer());
            }

            if (empty($user->user_passwordreset_token) || $this->input->post('token') !== $user->user_passwordreset_token) {
                $this->session->set_flashdata('alert_error', trans('loginalert_wrong_auth_code'));
                redirect($this->_get_safe_referer());
            }

            // Call the save_change_password() function from users model
            $this->mdl_users->save_change_password(
                $user_id,
                $new_password
            );

            // Update the user and set him active again
            $db_array = [
                'user_passwordreset_token' => '',
            ];

            //delete failed attempts from login_log table
            $user = $this->db->where('user_id', $user_id)->get('ip_users')->row();
            $this->_login_log_reset($user->user_email);

            $this->db->where('user_id', $user_id);
            $this->db->update('ip_users', $db_array);

            // Redirect back to the login form
            redirect('sessions/login');
        }

        // Check if the password reset form was used
        if ($this->input->post('btn_reset', true)) {
            $email = $this->input->post('email', true);

            // Validate email format first
            if ( ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                log_message('error', trans('log_invalid_email_format') . ': ' . $email . ' from IP: ' . $this->input->ip_address());
                redirect('sessions/login');
            }

            if (empty($email)) {
                log_message('warning', trans('log_empty_email_submitted') . ' from IP: ' . $this->input->ip_address());
                redirect('sessions/login');
            }

            // Security: Block automated tools and bots
            if ($this->_is_bot_request()) {
                log_message('warning', trans('log_password_reset_bot_detected') . ': ' . $this->input->ip_address() . ' User-Agent: ' . $this->input->user_agent());
                redirect('sessions/login');
            }

            // Security: Check IP-based rate limiting first (prevents email enumeration)
            if ($this->_is_ip_rate_limited_password_reset()) {
                log_message('warning', trans('log_password_reset_ip_rate_limit') . ' from: ' . $this->input->ip_address());
                redirect('sessions/login');
            }

            // Security: Prevent brute force attacks by counting password reset attempts per email
            if ($this->_is_email_rate_limited_password_reset($email)) {
                log_message('warning', trans('log_password_reset_email_rate_limit') . ' for: ' . $email . ' from IP: ' . $this->input->ip_address());
                redirect('sessions/login');
            }

            // Record the password reset attempt (both IP and email)
            $this->_record_password_reset_attempt();
            $this->_record_email_password_reset_attempt($email);

            // Test if a user with this email exists
            $this->db->where('user_email', $email);
            $user = $this->db->get('ip_users')->row();

            // Security: Always show the same message regardless of whether email exists
            // This prevents email enumeration attacks
            if ($user) {
                // User exists - send actual reset email
                //use salt to prevent predictability of the reset token (CVE-2021-29023)
                $this->load->library('crypt');
                $token = md5(time() . $email . $this->crypt->salt());

                // Save the token to the database
                $db_array = [
                    'user_passwordreset_token' => $token,
                ];

                $this->db->where('user_email', $email);
                $this->db->update('ip_users', $db_array);

                // Send the email with reset link
                $this->load->helper('mailer');

                // Prepare some variables for the email
                $email_resetlink = site_url('sessions/passwordreset/' . $token);
                $email_message   = $this->load->view('emails/passwordreset', [
                    'resetlink' => $email_resetlink,
                ], true);

                $email_from = get_setting('smtp_mail_from');
                if (empty($email_from)) {
                    $email_from = 'system@' . preg_replace("/^[\w]{2,6}:\/\/([\w\d\.\-]+).*$/", '$1', base_url());
                }

                // Mail the reset link with the pre-configured mailer if possible
                if (mailer_configured()) {
                    $this->load->helper('mailer/phpmailer');

                    if ( ! phpmail_send($email_from, $email, trans('password_reset'), $email_message)) {
                        $email_failed = true;
                    }
                } else {
                    $this->load->library('email');

                    // Set email configuration
                    $config['mailtype'] = 'html';
                    $this->email->initialize($config);

                    // Set the email params
                    $this->email->from($email_from);
                    $this->email->to($email);
                    $this->email->subject(trans('password_reset'));
                    $this->email->message($email_message);

                    // Send the reset email
                    if ( ! $this->email->send()) {
                        $email_failed = true;
                        log_message('error', $this->email->print_debugger());
                    }
                }

                // Show appropriate message
                if (isset($email_failed)) {
                    $this->session->set_flashdata('alert_error', trans('password_reset_failed'));
                } else {
                    $this->session->set_flashdata('alert_success', trans('email_successfully_sent'));
                }
            } else {
                // User doesn't exist - show same success message to prevent enumeration
                // DO NOT send email to prevent abuse and RBL issues
                $this->session->set_flashdata('alert_success', trans('email_successfully_sent'));
                log_message('info', trans('log_password_reset_nonexistent_email') . ': ' . $email . ' from IP: ' . $this->input->ip_address());
            }

            redirect('sessions/login');
        }

        return $this->load->view('session_passwordreset');
    }

    /**
     * Checks if the login_log table has records for the
     * given.
     *
     * @param string $username
     *
     * @return object
     */
    private function _login_log_check($username)
    {
        $login_log_query = $this->db->where('login_name', $username)->get('ip_login_log')->row();

        if ( ! empty($login_log_query) && $login_log_query->log_count > 10) {
            $current_time = new DateTime();
            $interval     = $current_time->diff(new DateTime($login_log_query->log_create_timestamp));
            //if the last recorded failed attempt is over 12 hours ago, then unlock the account
            //the fails are only counted up to 11, this means that the account is also unlocked
            //if the last failed 11th login attempt is over 12 hours ago.
            if ($interval->h > 12) {
                $this->_login_log_reset($username);

                return;
            }
        }

        return $login_log_query;
    }

    /**
     * Check if IP address has exceeded rate limit for password resets using session storage
     *
     * @param int $max_attempts Maximum attempts allowed per hour
     * @param int $window_minutes Time window in minutes
     *
     * @return bool True if rate limited, false otherwise
     */
    private function _is_ip_rate_limited_password_reset()
    {
        $max_attempts = env('PASSWORD_RESET_IP_MAX_ATTEMPTS', 5);
        $window_minutes = env('PASSWORD_RESET_IP_WINDOW_MINUTES', 60);

        $ip_address = $this->input->ip_address();
        $session_key = 'password_reset_attempts_' . md5($ip_address);

        // Get current attempts from session
        $attempts = $this->session->userdata($session_key);

        if (!$attempts) {
            $attempts = [];
        }

        // Clean up old attempts outside the time window
        $cutoff_time = time() - ($window_minutes * 60);
        $attempts = array_filter($attempts, function($timestamp) use ($cutoff_time) {
            return $timestamp > $cutoff_time;
        });

        // Check if rate limited
        if (count($attempts) >= $max_attempts) {
            log_message('info', trans('log_ip_rate_limit_check') . ': ' . count($attempts) . ' attempts from IP: ' . $ip_address);
            return true;
        }

        return false;
    }

    /**
     * Record a password reset attempt for the current IP
     */
    private function _record_password_reset_attempt()
    {
        $ip_address = $this->input->ip_address();
        $session_key = 'password_reset_attempts_' . md5($ip_address);

        // Get current attempts from session
        $attempts = $this->session->userdata($session_key);

        if (!$attempts) {
            $attempts = [];
        }

        // Add current timestamp
        $attempts[] = time();

        // Store back to session
        $this->session->set_userdata($session_key, $attempts);
    }

    /**
     * Check if email-based rate limit exceeded for password resets using session storage
     *
     * @param string $email Email address to check
     * @param int $max_attempts Maximum attempts allowed
     * @param int $window_hours Time window in hours
     *
     * @return bool True if rate limited, false otherwise
     */
    private function _is_email_rate_limited_password_reset($email)
    {
        $max_attempts = env('PASSWORD_RESET_EMAIL_MAX_ATTEMPTS', 3);
        $window_hours = env('PASSWORD_RESET_EMAIL_WINDOW_HOURS', 1);

        $session_key = 'password_reset_email_' . md5($email);

        // Get current attempts from session
        $attempts = $this->session->userdata($session_key);

        if (!$attempts) {
            $attempts = [];
        }

        // Clean up old attempts outside the time window
        $cutoff_time = time() - ($window_hours * 3600);
        $attempts = array_filter($attempts, function($timestamp) use ($cutoff_time) {
            return $timestamp > $cutoff_time;
        });

        // Check if rate limited
        if (count($attempts) >= $max_attempts) {
            log_message('info', trans('log_email_rate_limit_check') . ': ' . count($attempts) . ' attempts for email: ' . $email);
            return true;
        }

        return false;
    }

    /**
     * Record a password reset attempt for a specific email
     *
     * @param string $email Email address
     */
    private function _record_email_password_reset_attempt($email)
    {
        $session_key = 'password_reset_email_' . md5($email);

        // Get current attempts from session
        $attempts = $this->session->userdata($session_key);

        if (!$attempts) {
            $attempts = [];
        }

        // Add current timestamp
        $attempts[] = time();

        // Store back to session
        $this->session->set_userdata($session_key, $attempts);
    }

    /**
     * Check if the current request is from an automated tool or bot
     *
     * @return bool True if bot/automated tool detected, false otherwise
     */
    private function _is_bot_request()
    {
        $user_agent = $this->input->user_agent();

        // List of common automated tools and bots
        $bot_signatures = [
            'curl',
            'wget',
            'python-requests',
            'go-http-client',
            'java/',
            'apache-httpclient',
            'okhttp',
            'httpclient',
            'bot',
            'spider',
            'crawler',
            'scraper',
            'postman',
            'insomnia',
            'paw/',
        ];

        // Check if user agent is empty (common with automated tools)
        if (empty($user_agent)) {
            return true;
        }

        // Check if user agent contains any bot signatures (case-insensitive)
        $user_agent_lower = strtolower($user_agent);
        foreach ($bot_signatures as $signature) {
            if (strpos($user_agent_lower, $signature) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * If the username has a record in the login_log
     * table the count is incremented by 1, otherwise
     * a record for the given user is created.
     *
     * @param string $username
     */
    private function _login_log_addfailure($username)
    {
        if (empty($login_log_check = $this->_login_log_check($username))) {
            //create the log
            $this->db->insert('ip_login_log', [
                'login_name'           => $username,
                'log_count'            => 1,
                'log_create_timestamp' => date('c'),
            ]);
        } else {
            //update the log
            $this->db->set([
                'log_count'            => $login_log_check->log_count + 1,
                'log_create_timestamp' => date('c'),
            ])
                ->where('login_name', $username)
                ->update('ip_login_log');
        }
    }

    /**
     * The record of the given user is deleted from the
     * login_log table.
     *
     * @param string $username
     */
    private function _login_log_reset($username)
    {
        $this->db->delete('ip_login_log', ['login_name' => $username]);
    }

    /**
     * Validates that a referer URL is from the same domain
     * to prevent open redirect vulnerabilities
     *
     * @param string $referer
     * @return string Safe redirect URL
     */
    private function _get_safe_referer($referer = '')
    {
        // Use provided referer or HTTP_REFERER
        $referer = empty($referer) ? ($_SERVER['HTTP_REFERER'] ?? '') : $referer;

        // If no referer, use default
        if (empty($referer)) {
            return 'sessions/passwordreset';
        }

        // Get base URL
        $base_url = base_url();

        // Check if referer starts with base URL (same domain)
        if (strpos($referer, $base_url) === 0) {
            return $referer;
        }

        // Referer is external or invalid, use safe default
        return 'sessions/passwordreset';
    }
}
