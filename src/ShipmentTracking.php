<?php

namespace DPN\DHLShipmentTracking;

use GuzzleHttp\Client;

class ShipmentTracking
{

    /**
     * Action get piece
     */
    const OPERATION_GET_PIECE = 'd-get-piece';

    /**
     * Action get piece detail
     */
    const OPERATION_GET_PIECE_DETAIL = 'd-get-piece-detail';

    /**
     * Action get signature
     */
    const OPERATION_SIGNATURE = 'd-get-signature';

    /**
     * Action status for public user
     */
    const OPERATION_STATUS_PUBLIC = 'get-status-for-public-user';

    /**
     * maximum of pieces queried at the same time
     */
    const MAX_PIECE_CNT = 20;

    /**
     * @var Credentials
     */
    protected $credentials;

    /**
     * @param Credentials $credentials
     */
    public function __construct(Credentials $credentials)
    {
        $this->credentials = $credentials;
    }

    /**
     * @param string $pieceNumber
     * @param string $language
     *
     * @return array
     */
    public function getDetails(string $pieceNumber, string $language = RequestBuilder::LANG_EN)
    {
        $data = $this->call(static::OPERATION_GET_PIECE, $pieceNumber, $language);
        $array = $this->getArray($data);

        if ( self::isMultiPiece( $pieceNumber ) ) {
            $pieces = [];
            foreach ($array['data'] as $piece) {
                $pieces[] = $piece['@attributes'];
            }

            return $pieces;
        }

        return $array['data']['@attributes'];
    }

    /**
     * @param string $pieceNumber
     * @param string $language
     *
     * @return array
     */
    public function getDetailsAndEvents(string $pieceNumber, string $language = RequestBuilder::LANG_EN)
    {
        $data = $this->call(static::OPERATION_GET_PIECE_DETAIL, $pieceNumber, $language);
        $array = $this->getArray($data);

        if ( self::isMultiPiece( $pieceNumber ) ) {
            $pieces = [];
            foreach ($array['data'] as $piece) {
                $events = @$piece['data']['data'];
                $pieces[] = [
                    'details' => $piece['@attributes'],
                    'events' => ! empty( $events ) ? $this->getEvents( $events ) : [],
                ];
            }

            return $pieces;
        }

        $events = @$array['data']['data']['data'];

        return ['details' => $array['data']['@attributes'], 'events' => !empty($events) ? $this->getEvents($events) : []];
    }

    /**
     * @param string $pieceNumber
     * @param string $language
     *
     * @return array
     */
    public function getSignature(string $pieceNumber, string $language = RequestBuilder::LANG_EN)
    {
        $data = $this->call(static::OPERATION_SIGNATURE, $pieceNumber, $language);
        $array = $this->getArray($data);

        if ( self::isMultiPiece( $pieceNumber ) ) {
            $pieces = [];
            foreach ($array['data'] as $piece) {
                $pieces[] = $piece['@attributes'];
            }

            return $pieces;
        }

        return $array['data']['@attributes'];
    }

    /**
     * @param string $pieceNumber
     * @param string $language
     *
     * @return array
     */
    public function getPublicDetails(string $pieceNumber, string $language = RequestBuilder::LANG_EN)
    {
        $data = $this->callPublic(static::OPERATION_STATUS_PUBLIC, $pieceNumber, $language);
        $array = $this->getArray($data);

        if ( self::isMultiPiece( $pieceNumber ) ) {
            $pieces = [];
            foreach ($array['data'] as $piece) {
                $events = @$piece['data']['data'];
                $pieces[] = [
                    'details' => $piece['@attributes'],
                    'events' => ! empty( $events ) ? $this->getEvents( $events ) : [],
                ];
            }

            return $pieces;
        }

        $events = @$array['data']['data']['data'];

        return ['details' => $array['data']['data']['@attributes'], 'events' => !empty($events) ? $this->getEvents($events) : []];
    }

    /**
     * @param string $pieceNumber
     *
     * @return bool
     */
    private static function isMultiPiece(string $pieceNumber)
    {
        return ( stripos( $pieceNumber, ';' ) !== false ) && ( count( explode( ';', $pieceNumber ) ) <= self::MAX_PIECE_CNT );
    }

    /**
     * @param array $data
     *
     * @return array
     */
    private function getEvents($data)
    {
        foreach ($data as $event) {
            $events[] = $event['@attributes'];
        }

        return array_reverse($events);
    }

    /**
     * @param string $operation
     * @param string $pieceCode
     * @param string $language
     *
     * @return string
     */
    private function call($operation, $pieceCode, string $language = RequestBuilder::LANG_EN)
    {
        $request = RequestBuilder::createRequestXML($operation, $this->credentials->tnt_user, $this->credentials->tnt_password, $language, $pieceCode);
        $client = new Client();
        $res = $client->request(
            'GET', $this->credentials->cig_endpoint . '?xml=' . urlencode($request), [
            'auth' => [$this->credentials->cig_user, $this->credentials->cig_password]
        ]
        );

        return $res->getBody();
    }

    /**
     * @param string $operation
     * @param string $pieceCode
     * @param string $language
     *
     * @return string
     */
    private function callPublic($operation, $pieceCode, string $language = RequestBuilder::LANG_EN)
    {
        $request = RequestBuilder::createRequestPublicXML($operation, $this->credentials->tnt_user, $this->credentials->tnt_password, $language, $pieceCode);
        $client = new Client();
        $res = $client->request(
            'GET', $this->credentials->cig_endpoint . '?xml=' . urlencode($request), [
            'auth' => [$this->credentials->cig_user, $this->credentials->cig_password]
        ]
        );

        return $res->getBody();
    }

    /**
     * @param string $xml
     *
     * @return array
     */
    private function getArray($xml)
    {
        $xml = simplexml_load_string($xml);
        $json = json_encode($xml);

        return json_decode($json, true);
    }
}
