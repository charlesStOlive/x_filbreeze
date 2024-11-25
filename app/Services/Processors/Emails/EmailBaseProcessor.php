<?php

namespace App\Services\Processors\Emails;

use App\Dto\MsGraph\EmailMessageDTO;

class EmailBaseProcessor {
    public  $msgUser;
    public  EmailMessageDTO $emailData;
    public  $email;


     protected function getResultKey() {
        return 'services_results.' . static::getKey(); // Appelle la méthode imposée par l'interface
    }

    protected function getServiceKey() {
        return 'services_options.' . static::getKey();
    }

    protected function setError($resaon):void {
        $this->email->setAttribute($this->getResultKey().'.success', false);
        $this->email->setAttribute($this->getResultKey(). '.reason', $resaon);
    }

    protected function setResult(string $keyName, string|array $value):void {
        $this->email->setAttribute($this->getResultKey().'.'.$keyName, $value);
    }
    protected function getResult(string $keyName, string $value, ):string {
        return $this->email->setAttribute($this->getResultKey().'.'.$keyName, $value);
    }

    protected function getService(string $keyName):string {
        return $this->email->getAttribute($this->getServiceKey().'.'.$keyName);
    }

}

