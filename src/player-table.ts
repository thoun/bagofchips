const isDebug = window.location.host == 'studio.boardgamearena.com' || window.location.hash.indexOf('debug') > -1;;
const log = isDebug ? console.log.bind(window.console) : function () { };

class PlayerTable {
    public playerId: number;
    public voidStock: VoidStock<Card>;
    public hand?: LineStock<Card>;
    public minus: LineStock<Card>;
    public discard: Deck<Card>;
    public plus: LineStock<Card>;
    public reservedChips?: LineStock<Chip>;
    public limitSelection: number | null = null;

    private currentPlayer: boolean;

    constructor(private game: BagOfChipsGame, player: BagOfChipsPlayer, reservePossible: boolean) {
        this.playerId = Number(player.id);
        this.currentPlayer = this.playerId == this.game.getPlayerId();

        let html = `
        <div id="player-table-${this.playerId}" class="player-table" style="--player-color: #${player.color};">
            <div id="player-table-${this.playerId}-name" class="name-wrapper">${player.name}</div>
        `;
        if (this.currentPlayer) {
            html += `
            <div class="block-with-text hand-wrapper">
                <div class="block-label">${_('Your hand')}</div>
                <div id="player-table-${this.playerId}-hand" class="hand cards"></div>
            </div>`;
        }
        html += `
            <div class="player-visible-cards">
                <div id="player-table-${this.playerId}-minus"></div>
                <div id="player-table-${this.playerId}-discard"></div>
                <div id="player-table-${this.playerId}-plus"></div>
            </div>
        </div>
        `;

        dojo.place(html, document.getElementById('tables'));

        if (this.currentPlayer) {
            const handDiv = document.getElementById(`player-table-${this.playerId}-hand`);
            this.hand = new LineStock<Card>(this.game.cardsManager, handDiv, {
                sort: (a: Card, b: Card) => a.points - b.points,
            });
            this.hand.onCardClick = (card: Card) => this.game.onHandCardClick(card);
            
            this.hand.addCards(player.hand);

        }
        this.voidStock = new VoidStock<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-name`));

        
        this.minus = new LineStock<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-minus`));
        this.minus.addCards(player.minus);
        this.discard = new Deck<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-discard`), {
            topCard: player.discard[0],
            cardNumber: player.discard.length,
        });
        this.plus = new LineStock<Card>(this.game.cardsManager, document.getElementById(`player-table-${this.playerId}-plus`));
        this.plus.addCards(player.plus);
    }

    public setHandSelectable(selectable: boolean) {
        this.hand.setSelectionMode(selectable ? 'multiple' : 'none');
    }
}