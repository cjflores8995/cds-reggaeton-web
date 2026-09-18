<?php
if(!function_exists("seoCollectionMinimumProducts")){
    function seoCollectionMinimumProducts(){
        return 6;
    }
}

if(!function_exists("seoClassicMinimumYear")){
    function seoClassicMinimumYear(){
        return 1990;
    }
}

if(!function_exists("seoClassicMaximumYear")){
    function seoClassicMaximumYear(){
        return 2009;
    }
}

if(!function_exists("seoCollectionDecadeIsValid")){
    function seoCollectionDecadeIsValid($decade){
        $decade = (int)$decade;

        return
            $decade >= 1990 &&
            $decade <= 2090 &&
            $decade % 10 === 0;
    }
}

if(!function_exists("seoCollectionDecadeUrl")){
    function seoCollectionDecadeUrl($decade){
        $decade = (int)$decade;

        return seoCollectionDecadeIsValid(
            $decade
        )
            ? seoUrl(
                "decada/" .
                $decade
            )
            : "";
    }
}

if(!function_exists("seoClassicCollectionUrl")){
    function seoClassicCollectionUrl(){
        return seoUrl(
            "reggaeton-clasico"
        );
    }
}
?>
