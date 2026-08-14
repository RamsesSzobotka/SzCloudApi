<?php

class Security{

    public static function isOwner(){
        if(!$user = auth("api")->user()){
            abort(401);
        }
        return $user;
    }
}