<?php

class Undo {
    public array $cardsIds;
    public array $rewardsIds;
    public ?array $payOneLess;

    public function __construct(array $cardsIds, array $rewardsIds, ?array $payOneLess) {
        $this->cardsIds = $cardsIds;
        $this->rewardsIds = $rewardsIds;
        $this->payOneLess = $payOneLess;
    }

}
?>