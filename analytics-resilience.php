<?php

if(!function_exists("analyticsResilienceGuard")){
    function analyticsResilienceGuard($callback, $fallback = false){
        try{
            if(!is_callable($callback)){
                return $fallback;
            }

            return $callback();
        }catch(Throwable $exception){
            return $fallback;
        }
    }
}

if(!function_exists("analyticsResilienceRecordServerEvent")){
    function analyticsResilienceRecordServerEvent($connection, $eventType, $options = []){
        return (bool)analyticsResilienceGuard(
            function() use ($connection, $eventType, $options){
                if(function_exists("analyticsPrivacySanitizeServerContext")){
                    analyticsPrivacySanitizeServerContext();
                }

                if(!function_exists("analyticsPhase2RecordServerEvent")){
                    return false;
                }

                return analyticsPhase2RecordServerEvent(
                    $connection,
                    $eventType,
                    is_array($options) ? $options : []
                );
            },
            false
        );
    }
}
