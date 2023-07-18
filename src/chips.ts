class ChipsManager extends CardManager<Chip> {
    constructor (public game: BagOfChipsGame) {
        super(game, {
            getId: (card) => `chip-${card.id}`,
            setupDiv: (card: Chip, div: HTMLElement) => {
                div.classList.add('chip');
                div.dataset.type = ''+card.color;
            },
            isCardVisible: () => true,
            cardWidth: 82,
            cardHeight: 98,
        });
    }
    
    public getHtml(card: Chip): string {
        let html = `<div class="card chip" data-side="front" data-type="${card.color}">
            <div class="card-sides">
                <div class="card-side front">
                </div>
            </div>
        </div>`;
        return html;
    }
}