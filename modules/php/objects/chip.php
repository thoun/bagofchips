<?php

class Chip {

    public int $id;
    public string $location;
    public int $locationArg;
    public ?int $color;

    public function __construct($dbCard) {
        if (gettype($dbCard) != 'array') {
            throw new Exception(gettype($dbCard). ' '. $dbCard);
        }
        $this->id = intval($dbCard['card_id'] ?? $dbCard['id']);
        $this->location = $dbCard['card_location'] ?? $dbCard['location'];
        $this->locationArg = intval($dbCard['card_location_arg'] ?? $dbCard['location_arg']);
        $this->color = array_key_exists('card_type', $dbCard) || array_key_exists('type', $dbCard) ? intval($dbCard['card_type'] ?? $dbCard['type']) : null;
    }
}

?>