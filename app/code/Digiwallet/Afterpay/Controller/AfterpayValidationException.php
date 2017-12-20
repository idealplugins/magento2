<?php
namespace Digiwallet\Afterpay\Controller;

class AfterpayValidationException extends \Exception
{

    /**
     * Array of message
     * 
     * @var unknown
     */
    private $messages;

    /**
     * The original message string
     * 
     * @var unknown
     */
    private $message_content;

    /**
     * The validation error exception
     * 
     * @param unknown $error_message            
     */
    public function __construct($error_message)
    {
        $this->message_content = $error_message;
    }

    /**
     * Get array of validation message
     * 
     * @return string[]
     */
    public function getErrorItems()
    {
        if ($this->IsValidationError()) {
            return $this->messages;
        }
        return null;
    }

    /**
     * *
     * Check if the message are Afterpay validation messages
     * 
     * @return boolean
     */
    public function IsValidationError()
    {
        if (is_array($this->message_content)) {
            foreach ($this->message_content as $key => $value) {
                $this->messages[] = ! empty($value['description']) ? $value['description'] : "";
            }
            return true;
        } else {
            $check_key = "DW_XE_0003 Validation failed, details:";
            if (strpos($this->message_content, $check_key) !== false) {
                $json_string = substr($this->message_content, strpos($this->message_content, "details:") + 8);
                $this->messages = json_decode($json_string, true);
                return true;
            }
        }
        return false;
    }
}