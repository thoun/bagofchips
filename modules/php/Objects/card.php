<?php

require_once(__DIR__.'/../constants.inc.php');

class CardType {
    public int $points;
    public array $params;
  
    public function __construct(int $points, array $params) {
        $this->points = $points;
        $this->params = $params;
    } 
}

class Card extends CardType {

    public int $id;
    public string $location;
    public int $locationArg;
    public ?int $type;
    public ?int $subType;

    public function __construct($dbCard, $CARDS) {
        $this->id = intval($dbCard['card_id'] ?? $dbCard['id']);
        $this->location = $dbCard['card_location'] ?? $dbCard['location'];
        $this->locationArg = intval($dbCard['card_location_arg'] ?? $dbCard['location_arg']);
        $this->type = array_key_exists('card_type', $dbCard) || array_key_exists('type', $dbCard) ? intval($dbCard['card_type'] ?? $dbCard['type']) : null;
        $this->subType = array_key_exists('card_type_arg', $dbCard) || array_key_exists('type_arg', $dbCard) ? intval($dbCard['card_type_arg'] ?? $dbCard['type_arg']) : null;

        if ($this->type !== null && $this->type !== null) {
            $cardType = $CARDS[$this->type][$this->subType];
            $this->points = $cardType->points;      
            $this->params = $cardType->params;
        }
    } 

    public static function onlyId(?Card $card) {
        if ($card == null) {
            return null;
        }
        
        return new Card([
            'card_id' => $card->id,
            'card_location' => $card->location,
            'card_location_arg' => $card->locationArg,
        ], null);
    }

    public static function onlyIds(array $cards) {
        return array_map(fn($card) => self::onlyId($card), $cards);
    }
}

?>