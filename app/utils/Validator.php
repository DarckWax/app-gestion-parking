<?php

namespace App\Utils;

/**
 * Input Validation Class
 */
class Validator
{
    private $errors = [];
    private $data = [];
    
    public function __construct($data = [])
    {
        $this->data = $data;
    }
    
    public function required($field, $message = null)
    {
        if (!isset($this->data[$field]) || empty(trim($this->data[$field]))) {
            $this->errors[$field] = $message ?: "The {$field} field is required.";
        }
        return $this;
    }
    
    public function email($field, $message = null)
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?: "The {$field} must be a valid email address.";
        }
        return $this;
    }
    
    public function min($field, $min, $message = null)
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $min) {
            $this->errors[$field] = $message ?: "The {$field} must be at least {$min} characters.";
        }
        return $this;
    }
    
    public function max($field, $max, $message = null)
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $max) {
            $this->errors[$field] = $message ?: "The {$field} may not be greater than {$max} characters.";
        }
        return $this;
    }
    
    public function numeric($field, $message = null)
    {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = $message ?: "The {$field} must be a number.";
        }
        return $this;
    }
    
    public function phone($field, $message = null)
    {
        if (isset($this->data[$field]) && !Security::validatePhone($this->data[$field])) {
            $this->errors[$field] = $message ?: "The {$field} must be a valid phone number.";
        }
        return $this;
    }
    
    public function datetime($field, $message = null)
    {
        if (isset($this->data[$field])) {
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $this->data[$field]);
            if (!$date || $date->format('Y-m-d H:i:s') !== $this->data[$field]) {
                $this->errors[$field] = $message ?: "The {$field} must be a valid datetime.";
            }
        }
        return $this;
    }
    
    public function unique($field, $table, $column, $excludeId = null, $message = null)
    {
        if (isset($this->data[$field])) {
            $db = \App\Core\Database::getInstance();
            $sql = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
            $params = [$this->data[$field]];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $result = $db->fetch($sql, $params);
            if ($result['count'] > 0) {
                $this->errors[$field] = $message ?: "The {$field} has already been taken.";
            }
        }
        return $this;
    }
    
    public function passes()
    {
        return empty($this->errors);
    }
    
    public function fails()
    {
        return !$this->passes();
    }
    
    public function getErrors()
    {
        return $this->errors;
    }
    
    public function getFirstError($field = null)
    {
        if ($field) {
            return $this->errors[$field] ?? null;
        }
        
        return !empty($this->errors) ? array_values($this->errors)[0] : null;
    }
}
