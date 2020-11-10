<?php
namespace App\Integrations\Finna;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Kayttaja;
use App\Ark\Loyto;

/**
 * Integraatio Museoviraston muinaisjäännösrekisterin rajapintapalveluun.
 */
class FinnaUtils
{
    private static $ALLOWEDPARAMETERS = ['from', 'until', 'resumptionToken', 'metadataPrefix', 'verb', 'identifier'];
    private static $SUPPORTEDMETATADAPREFIX = 'lido';
    private static $SUPPORTEDVERBS = ['Identify', 'ListRecords', 'ListIdentifiers', 'GetRecord'];

    /**
     * Returns the given date as UTC-date string.
     * https://stackoverflow.com/questions/17390784/how-to-format-an-utc-date-to-use-the-z-zulu-zone-designator-in-php
     *
     * @param \DateTimeInterface $date
     * @return string
     */
    public static function dateTo8601Zulu(\DateTimeInterface $date): string {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    public static function setFinnaUser() {
        // Haetaan tiedot "Finna" systeemikäyttäjänä
        $user = Kayttaja::find(- 2); // Finna käyttäjä
        $token = JWTAuth::fromUser($user); // Luodaan token
        JWTAuth::setToken($token); // Asetetaan token
        $user = JWTAuth::toUser(); // Asetetaan kirjautunut käyttäjä
        // Log::debug(Auth::user());
    }

    /**
     * Returns an <error></error> element for the OAI-PMH XML if the validation is NOT passed
     * If the validation is passed, return null.
     * @param Request $request
     * @return NULL|mixed|mixed|NULL
     */
    public static function validateRequest(Request $request) {
        // 1: Check that the OAI verb is present and valid
        $invalidVerbResponse = self::validateVerb($request);
        if ($invalidVerbResponse) {
            return $invalidVerbResponse;
        }

        // 2. Validate SET
        $invalidSetResponse = self::validateSet($request);
        if ($invalidSetResponse) {
            return $invalidSetResponse;
        }

        // 3. Validate the possible resumptionToken
        $invalidResumptionToken = self::validateResumptionToken($request);
        if ($invalidResumptionToken) {
            return $invalidResumptionToken;
        }

        // 4. Validate that both the resumptionToken and metadataPrefix are not present
        $invalidMetadataPrefixAndResumptionToken = self::validateMetadataPrefixAndResumptionToken($request);
        if($invalidMetadataPrefixAndResumptionToken) {
            return $invalidMetadataPrefixAndResumptionToken;
        }

        // 5. Validate that doesn't have invalid parameters
        $invalidParameters = self::validateHasNoInvalidParameters($request);
        if($invalidParameters) {
            return $invalidParameters;
        }

        // 6. Validate that doesn't have duplicate parameters
        $invalidDuplicateParameters = self::validateHasNoDuplicateParameters($request);
        if($invalidDuplicateParameters) {
            return $invalidDuplicateParameters;
        }

        // 7. Validate from if present
        // 8. Validate until if present

        // SERVICE BASED VALIDATIONS
        $verb = $request->all()['verb'];
        if($verb == 'Identify') {
            $invalidIdentifyRequest = self::validateIdentifyRequest($request);
            if($invalidIdentifyRequest) {
                return $invalidIdentifyRequest;
            }
        } else if ($verb == 'ListRecords') {
            $invalidListRecordsRequest = self::validateListRecordsRequest($request);
            if($invalidListRecordsRequest) {
                return $invalidListRecordsRequest;
            }
        } else if ($verb == 'ListIdentifiers') {
            $invalidListIdentifiersRequest = self::validateListIdentifiersRequest($request);
            if($invalidListIdentifiersRequest) {
                return $invalidListIdentifiersRequest;
            }
        } else if ($verb == 'GetRecord') {
            $invalidGetRecordRequest = self::validateGetRecordRequest($request);
            if($invalidGetRecordRequest) {
                return $invalidGetRecordRequest;
            }
        } else {
                return self::writeErrorXml($request->url(), 'badVerb', 'The verb argument is missing');
        }
    }

    public static function validateIdentifyRequest($request) {
        $parameters = $request->all();
        // parameters contain verb=Identify
        if (sizeof($parameters) > 1) {
            return self::writeErrorXml($request->url, 'badArgument', 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax: allowed parameter count: 0');
        }
        return null;
    }

    public static function validateListRecordsRequest($request) {
        $parameters = $request->all();
        if (array_key_exists('metadataPrefix', $parameters) && $parameters['metadataPrefix'] != self::$SUPPORTEDMETATADAPREFIX) {
            return self::writeErrorXml($request->url(), 'badArgument', 'Only '.self::$SUPPORTEDMETATADAPREFIX . ' supported');
        }

        $containsResumptionTokenOrMetadataPrefix = self::containsResumptionTokenOrMetadataPrefix($parameters);
        if(!$containsResumptionTokenOrMetadataPrefix) {
            return self::writeErrorXml($request->url(), 'badArgument', 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax: No metadataPrefix or resumptionToken');
        }

        return null;
    }

    public static function validateListIdentifiersRequest($request) {
        $parameters = $request->all();
        if (array_key_exists('metadataPrefix', $parameters) && $parameters['metadataPrefix'] != self::$SUPPORTEDMETATADAPREFIX) {
            return self::writeErrorXml($request->url(), 'badArgument', 'Only '.self::$SUPPORTEDMETATADAPREFIX . ' supported');
        }
        // Jos ei ole resumptionToken parametria, tulee olla metadataPrefix
        $containsResumptionTokenOrMetadataPrefix = self::containsResumptionTokenOrMetadataPrefix($parameters);
        if(!$containsResumptionTokenOrMetadataPrefix) {
            return self::writeErrorXml($request->url(), 'badArgument', 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax: No metadataPrefix or resumptionToken');
        }

        return null;
    }

    public static function validateGetRecordRequest($request) {
        $parameters = $request->all();

        // has to contain metadataprefix
        if(!array_key_exists('metadataPrefix', $parameters)) {
            return self::writeErrorXml($request->url(), 'badArgument', 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax: No metadataPrefix');
        }
        if(!array_key_exists('identifier', $parameters)) {
            return self::writeErrorXml($request->url(), 'badArgument', 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax: No identifier');
        }
        if(!self::validateIdentifier($parameters['identifier'])) {
            return self::writeErrorXml($request->url(), 'idDoesNotExist', 'The value of the identifier argument is unknown or illegal in this repository: ' . $parameters['identifier']);
        }
        if(!self::validateLoyto(explode('.', $parameters['identifier'])[3])) {
            return self::writeErrorXml($request->url(), 'idDoesNotExist', 'The value of the identifier argument is unknown or illegal in this repository: ' . $parameters['identifier']);
        }
    }

    // Identifier format: oai:mip.turku.fi:17.123
    private static function validateIdentifier($identifier) {
        // Check that it contains the correct 'beginning' part
        if(strpos($identifier, 'oai:mip.turku.fi:17') === false) {
            return false;
        }
        // Check that it's length is correct
        $identifierArr = explode('.', $identifier);
        if(sizeof($identifierArr) != 4) {
            return false;
        }
        // Check that the löytö id part is numeric
        if(!is_numeric($identifierArr[3])) {
            return false;
        }

        return true;
    }

    private static function validateLoyto($loytoId) {
        // Validoidaan, että löytö löytyy ja kuuluu tutkimukseen joka on julkinen ja valmis
        $loyto = Loyto::getSingle($loytoId)->with(array(
            'tutkimusalue.tutkimus',
            'yksikko.tutkimusalue.tutkimus'
        ))->first();
        if(!$loyto) {
            Log::channel('finna')->info("Loyto: " . $loytoId . " ei ole olemassa");
            return false;
        } else {
            // Löytö löytyy
            // Tarkastetaan onko sen siirtyy_finnaan = true
            if($loyto->siirtyy_finnaan == false) {
                Log::channel('finna')->info("Loyto: " . $loytoId . ", siirtyy_finnaan: ". var_export($loyto->siirtyy_finnaan, true));
                return false;
            }

            // Tarkastetaan tutkimuksen julkisuus + valmius
            $tutkimus = null;
            if($loyto->yksikko && $loyto->yksikko->tutkimusalue && $loyto->yksikko->tutkimusalue->tutkimus) {
                $tutkimus = $loyto->yksikko->tutkimusalue->tutkimus;
            } else if($loyto->tutkimusalue && $loyto->tutkimusalue->tutkimus) {
                $tutkimus = $loyto->tutkimusalue->tutkimus;
            } else {
                Log::channel('finna')->info("Loyto: " . $loytoId . " / Löydön tutkimusta ei löydy.");
                return false; // Ei tutkimusta -> ei validi ollenkaan
            }
            if(!$tutkimus->julkinen || !$tutkimus->valmis) {
                Log::channel('finna')->info("Loyto: " . $loytoId . " / Tutkimus: ". $tutkimus->nimi. ", julkinen: " . var_export($tutkimus->julkinen, true) . ", valmis: " . var_export($tutkimus->valmis, true));
                return false; // Tutkimus ei ole julkinen ja valmis
            }
        }
        return true;
    }

    public static function getCursorPositionFromParameters($resumptionToken) {
        // Resumption token is in format of 'finna.17.cursorposition', so we need the last element
        $rtArr = explode('.', $resumptionToken);
        Log::channel('finna')->info("Cursor position (parsed from resumptionToken in request): " . $rtArr[2]);

        return $rtArr[2];
    }

    private static function containsResumptionTokenOrMetadataPrefix($parameters) {
        if (!array_key_exists('resumptionToken', $parameters) && !array_key_exists('metadataPrefix', $parameters)) {
         return false;
        }
        return true;
    }

    private static function validateVerb($request) {
        $parameters = $request->all();

        $verb = array_key_exists('verb', $parameters) === true ? $parameters['verb'] : null;
        if ($verb) {
            if ($verb != 'Identify' && $verb != 'ListRecords' && $verb != 'ListIdentifiers' && $verb != 'GetRecord') {
                return self::writeErrorXml($request->url(), 'badVerb', 'Illegal OAI verb : ' . $verb);
            }
        } else {
            return self::writeErrorXml($request->url(), 'badVerb', 'The verb argument is missing');
        }
        return null;
    }

    private static function validateSet($request) {
        $parameters = $request->all();
        $verb = array_key_exists('verb', $parameters) === true ? $parameters['verb'] : null;

        // We do not support SET
        if ($verb == 'ListSets') {
            return self::writeErrorXml($request->url(), 'noSetHierarchy', 'This repository does not support sets');
        }

        return null;
    }

    /*
     * Resumption token format:
     * 'finna.17.1000'
     * 1: finna (to indicate it's for finna)
     * 2. indicates entity = löytö
     * 3. indicates the cursor position
     */
    private static function validateResumptionToken($request) {
        $parameters = $request->all();
        $resumptionToken = array_key_exists('resumptionToken', $parameters) == true ? $parameters['resumptionToken'] : null;

        if($resumptionToken) {
            $rsArr = explode('.', $resumptionToken);
            if (sizeof($rsArr) != 3 || $rsArr[0] != 'finna') {
                return self::writeErrorXml($request->url(), 'badResumptionToken', 'The resumptionToken is invalid: ' . $parameters['resumptionToken']);
            }
        }
    }

    private static function writeErrorXml($requestPath, $errorCode, $errorMessage)
    {
        $template = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"
         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/
         http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
  <responseDate></responseDate>
  <request></request>
  <error></error>
</OAI-PMH>
XML;

        $responseDate = self::dateTo8601Zulu(Carbon::now());
        $xml = simplexml_load_string($template);
        $xml->responseDate = $responseDate;
        $xml->request = $requestPath;
        $xml->error->addAttribute('code', $errorCode);
        $xml->error = $errorMessage;
        return $xml->asXML();
    }

    private static function validateMetadataPrefixAndResumptionToken($request) {
        $parameters = $request->all();

        // Ei saa olla resumptionToken ja metadataPrefix samassa pyynnössä
        if (array_key_exists('resumptionToken', $parameters) && array_key_exists('metadataPrefix', $parameters)) {
            return self::writeErrorXml($request->url(), 'badArgument', 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax: ' . $parameters['resumptionToken'] . " & " . $parameters['metadataPrefix']);
        }
        return null;
    }

    private static function validateHasNoInvalidParameters($request) {
        $parameters = $request->all();

        // Ei saa olla muita kuin sallittuja elementtejä pyynnössä
        $check = count(array_intersect(array_keys($parameters), self::$ALLOWEDPARAMETERS)) == count(array_keys($parameters));
        if (!$check) {
            return self::writeErrorXml($request->url(), 'badArgument', 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax: allowed parameters: ' . implode(',', self::$ALLOWEDPARAMETERS));
        }
        return null;
    }

    private static function validateHasNoDuplicateParameters($request) {
        // TODO: Laravel ylikirjoittaa duplikaatit parametrit ja ainoastaan viimeinen jää
        // voimaan - jos tätä tarvitaan niin miten requestista saa alkuperäiset parametrit?
        // https://laracasts.com/discuss/channels/general-discussion/how-can-we-parse-multiple-url-params-with-the-same-value-in-laravel
        // if(count($parameterKeys) != count(array_unique($parameterKeys))) {
        // return self::writeErrorXml($request->url(), 'badArgument', 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax.');
        return null;
    }

    /*
     * Returns the XML template for the identify method.
     * Values are to be filled in later on.
     * Source: https://www.kiwi.fi/display/Finna/Finna+ja+OAI-PMH
     */
    public static function getIdentifyXml()
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"
   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
   xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/
   http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">
  <responseDate></responseDate>
  <request verb="Identify"></request>
  <Identify>
    <repositoryName></repositoryName>
    <baseURL></baseURL>
    <protocolVersion></protocolVersion>
    <adminEmail></adminEmail>
    <earliestDatestamp></earliestDatestamp>
    <deletedRecord>persistent</deletedRecord>
    <granularity></granularity>
    <compression></compression>
    <description>
      <oai-identifier
        xmlns="http://www.openarchives.org/OAI/2.0/oai-identifier"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai-identifier
        http://www.openarchives.org/OAI/2.0/oai-identifier.xsd">
        <scheme></scheme>
        <repositoryIdentifier></repositoryIdentifier>
        <delimiter></delimiter>
        <sampleIdentifier></sampleIdentifier>
      </oai-identifier>
    </description>
  </Identify>
</OAI-PMH>
XML;
    }
}