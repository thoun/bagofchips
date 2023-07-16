class ChipsManager extends CardManager<Chip> {
    constructor (public game: BagOfChipsGame) {
        super(game, {
            getId: (card) => `chip-${card.id}`,
            setupDiv: (card: Chip, div: HTMLElement) => {
                div.classList.add('chip');
                div.dataset.type = ''+card.color;
            },
            isCardVisible: () => true,
            cardWidth: 254,
            cardHeight: 354,
        });
    }
}