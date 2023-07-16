/**
 * Your game interfaces
 */

interface Card {
    id: number;
    location: string;
    locationArg: number;
    color: number;
    gain: number;
}

interface Chip {
    id: number;
    location: string;
    locationArg: number;
    type: number;
    number: number;
    cost: { [color: number]: number };
    immediateGains: { [type: number]: number };
    gains: (number | null)[];
}

interface BagOfChipsPlayer extends Player {
    playerNo: number;
    reputation: number;
    recruit: number;
    bracelet: number;
    //handCount: number;
    hand?: Card[];
    playedCards: { [color: number]: Card[] };
    chips: Chip[];
    reservedChips?: Chip[];
}

interface BagOfChipsGamedatas {
    current_player_id: string;
    decision: {decision_type: string};
    game_result_neutralized: string;
    gamestate: Gamestate;
    gamestates: { [gamestateId: number]: Gamestate };
    neutralized_player_id: string;
    notifications: {last_packet_id: string, move_nbr: string}
    playerorder: (string | number)[];
    players: { [playerId: number]: BagOfChipsPlayer };
    tablespeed: string;

    // Add here variables you set up in getAllDatas
    cardDeckTop?: Card;
    cardDeckCount: number;
    cardDiscardCount: number;
    centerCards: Card[];
    centerChipsDeckTop: { [letter: string]: Chip };
    centerChipsDeckCount: { [letter: string]: number };
    centerChips: { [letter: string]: Chip[] };
    boatSideOption: number;
    variantOption: number;
    artifacts?: number[];
    firstPlayerId: number;
    lastTurn: boolean;
    reservePossible: boolean;
}

interface BagOfChipsGame extends Game {
    cardsManager: CardsManager;
    chipsManager: ChipsManager;

    getPlayerId(): number;
    getPlayer(playerId: number): BagOfChipsPlayer;
    //getGain(type: number): string;
    //getColor(color: number): string;
    getTooltipGain(type: number): string;
    getTooltipColor(color: number): string;
    getGameStateName(): string;
    getCurrentPlayerTable(): PlayerTable | null;

    setTooltip(id: string, html: string): void;
    highlightPlayerTokens(playerId: number | null): void;
    onTableChipClick(chip: Chip): void;
    onHandCardClick(card: Card): void;
    onTableCardClick(card: Card): void;
    onPlayedCardClick(card: Card): void;
}

interface EnteringPlayActionArgs {
    canRecruit: boolean;
    canExplore: boolean;
    canTrade: boolean;
    possibleChips: Chip[];
}

interface EnteringChooseNewCardArgs {
    centerCards: Card[];
    freeColor: number;
    recruits: number;
    allFree: boolean;
}

interface EnteringPayChipArgs {
    selectedChip: Chip;
    recruits: number;
}

interface EnteringTradeArgs {
    bracelets: number;
    gainsByBracelets: { [bracelets: number]: number };
}

// playCard
interface NotifPlayCardArgs {
    playerId: number;
    card: Card;
    newHandCard: Card;
    effectiveGains: { [type: number]: number };
}

// card
interface NotifNewCardArgs {
    playerId: number;
    card: Card;
    cardDeckTop?: Card;
    cardDeckCount: number;
}

// takeChip
interface NotifTakeChipArgs {
    playerId: number;
    chip: Chip;
    effectiveGains: { [type: number]: number };
}

// newTableChip
interface NotifNewTableChipArgs {
    chip: Chip;
    letter: string;    
    chipDeckTop?: Chip;
    chipDeckCount: number;
}

// trade
interface NotifTradeArgs {
    playerId: number;
    effectiveGains: { [type: number]: number };
}

// discardCards
interface NotifDiscardCardsArgs {
    playerId: number;
    cards: Card[];
    cardDiscardCount: number;
}

// discardTableCard
interface NotifDiscardTableCardArgs {
    card: Card;
}

// reserveChip
interface NotifReserveChipArgs {
    playerId: number;
    chip: Chip;
}

// score
interface NotifScoreArgs {
    playerId: number;
    newScore: number;
    incScore: number;
}

// cardDeckReset
interface NotifCardDeckResetArgs {  
    cardDeckTop?: Card;
    cardDeckCount: number;
    cardDiscardCount: number;
}
