<?php
/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @author      Jan Kozak <galvani78@gmail.com>
 */

namespace MauticPlugin\MauticWhatsappBundle\Transport;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\SmsBundle\Api\AbstractSmsApi;
use Monolog\Logger;
use GuzzleHttp\Client;

class WhatsappTransport extends AbstractSmsApi
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var IntegrationHelper
     */
    protected $integrationHelper;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    private $api_key;

    /**
     * @var string
     */
    private $sender_id;

    /**
     * @var bool
     */
    protected $connected;

    /**
     * @param IntegrationHelper $integrationHelper
     * @param Logger            $logger
     * @param Client            $client
     */
    public function __construct(IntegrationHelper $integrationHelper, Logger $logger, Client $client)
    {
        $this->integrationHelper = $integrationHelper;
        $this->logger = $logger;
        $this->client = $client;
        $this->connected = false;
    }

    /**
     * @param Lead   $contact
     * @param string $content
     *
     * @return bool|string
     */
    public function sendSms(Lead $contact, $content)
    {
        $number = $contact->getLeadPhoneNumber();
        if (empty($number)) {
            return false;
        }

        try {
            $number = substr($this->sanitizeNumber($number), 1);
        } catch (NumberParseException $e) {
            $this->logger->addInfo('Invalid number format. ', ['exception' => $e]);
            return $e->getMessage();
        }

        try {
            if (!$this->connected && !$this->configureConnection()) {
                throw new \Exception("Whatsapp MSG is not configured properly.");
            }

            $content = $this->sanitizeContent($content, $contact);
            if (empty($content)) {
                throw new \Exception('Message content is Empty.');
            }

            $response = $this->send($number, $content);
            $this->logger->addInfo("Whatsapp MSG request succeeded. ", ['response' => $response]);
            return true;
        } catch (\Exception $e) {
            $this->logger->addError("Whatsapp MSG request failed. ", ['exception' => $e]);
            return $e->getMessage();
        }
    }

    /**
     * @param integer   $number
     * @param string    $content
     * 
     * @return array
     * 
     * @throws \Exception
     */
    protected function send($number, $content)
    {


$url='http://116.203.191.58/api/send_message';
$data = array(
  "phone_no"  => $number,
  "phone_no"  => '+'.$number,
  "key"       => $this->api_key,
  "message"   => $content,
  "skip_link" => True // This optional for skip snapshot of link in message
);
$data_string = json_encode($data);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, 0);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
curl_setopt($ch, CURLOPT_TIMEOUT, 360);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
  'Content-Type: application/json',
  'Content-Length: ' . strlen($data_string))
);
echo $res=curl_exec($ch);
curl_close($ch);

        $this->logger->addInfo("Whatsapp MSG API request intiated. ", ['url' => $url]);
    }

    /**
     * @param string $number
     *
     * @return string
     *
     * @throws NumberParseException
     */
    protected function sanitizeNumber($number)
    {
        $util = PhoneNumberUtil::getInstance();
        $parsed = $util->parse($number, 'IN');

        return $util->format($parsed, PhoneNumberFormat::E164);
    }

    /**
     * @return bool
     */
    protected function configureConnection()
    {
        $integration = $this->integrationHelper->getIntegrationObject('Whatsapp');
        if ($integration && $integration->getIntegrationSettings()->getIsPublished()) {
            $keys = $integration->getDecryptedApiKeys();
            // if (empty($keys['api_key']) || empty($keys['sender_id'])) {
            if (empty($keys['api_key']) ) {
	    return false;
            }
            $this->api_key = $keys['api_key'];
//            $this->sender_id = $keys['sender_id'];
            $this->connected = true;
        }
        return $this->connected;
    }

    /**
     * @param string $content
     * @param Lead   $contact
     *
     * @return string
     */
    protected function sanitizeContent(string $content, Lead $contact) {
        return strtr($content, array(
            '{contact_title}' => $contact->getTitle(),
            '{conact_firstname}' => $contact->getFirstname(),
            '{contact_lastname}' => $contact->getLastname(),
            '{contact_lastname}' => $contact->getName(),
            '{contact_company}' => $contact->getCompany(),
            '{contact_email}' => $contact->getEmail(),
            '{contact_address1}' => $contact->getAddress1(),
            '{contact_address2}' => $contact->getAddress2(),
            '{contact_city}' => $contact->getCity(),
            '{contact_state}' => $contact->getState(),
            '{contact_country}' => $contact->getCountry(),
            '{contact_zipcode}' => $contact->getZipcode(),
            '{contact_location}' => $contact->getLocation(),
            '{contact_phone}' => $contact->getLeadPhoneNumber(),
        ));
    }
}
