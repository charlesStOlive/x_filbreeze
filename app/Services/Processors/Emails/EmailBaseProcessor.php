<?php

namespace App\Services\Processors\Emails;

use App\Models\MsgUserIn;
use App\Models\MsgEmailIn;
use App\Dto\MsGraph\EmailMessageDTO;



class EmailBaseProcessor {
    public MsgUserIn $msgUser;
    public EmailMessageDTO $email;
    public MsgEmailIn $emailIn;


     protected function getResultKey() {
        return 'results.' . static::getKey(); // Appelle la méthode imposée par l'interface
    }

    protected function getServiceKey() {
        return 'services.' . static::getKey();
    }

    protected function setError($resaon):void {
        $this->emailIn->setAttribute($this->getResultKey().'.success', false);
        $this->emailIn->setAttribute($this->getResultKey(). '.reason', $resaon);
    }

    protected function setResult(string $keyName, string $value):void {
        $this->emailIn->setAttribute($this->getResultKey().'.'.$keyName, $value);
    }
    protected function getResult(string $keyName, string $value, ):string {
        return $this->emailIn->setAttribute($this->getResultKey().'.'.$keyName, $value);
    }

    protected function getService(string $keyName):string {
        return $this->emailIn->getAttribute($this->getServiceKey().'.'.$keyName);
    }

}

