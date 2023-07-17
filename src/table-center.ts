class TableCenter {
    public chips: LineStock<Chip>[] = [];
        
    constructor(private game: BagOfChipsGame, gamedatas: BagOfChipsGamedatas) {
        const tableCenter = document.getElementById(`table-center`);
        [1, 2, 3, 4].forEach(phase => {
            tableCenter.insertAdjacentHTML('beforeend', `
                <div id="map${phase}" class="map" data-phase="${phase}"></div>
            `);
        });

        [1, 2, 3, 4, 5].forEach(phase => {
            const map = Math.min(4, phase);
            document.getElementById(`map${map}`).insertAdjacentHTML('beforeend', `
                <div id="slot${phase}" class="slot"></div>
            `);

            this.chips[phase] = new LineStock<Chip>(game.chipsManager, document.getElementById(`slot${phase}`));
            this.chips[phase].addCards(gamedatas.chips.filter(chip => chip.locationArg == phase));
        });
    }
    
    public revealChips(slot: number, chips: Chip[]): Promise<any> {
        return this.chips[slot].addCards(chips);
    }
    
    public endTurn() {
        [1, 2, 3, 4, 5].forEach(phase => this.chips[phase].removeAll());
    }
}