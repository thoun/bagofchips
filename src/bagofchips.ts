declare const define;
declare const ebg;
declare const $;
declare const dojo: Dojo;
declare const _;
declare const g_gamethemeurl;

const ANIMATION_MS = 500;
const ACTION_TIMER_DURATION = 5;

const LOCAL_STORAGE_ZOOM_KEY = 'BagOfChips-zoom';
const LOCAL_STORAGE_JUMP_TO_FOLDED_KEY = 'BagOfChips-jump-to-folded';

const EQUAL = -1;
const DIFFERENT = 0;

const VP = 1;
const BRACELET = 2;
const RECRUIT = 3;
const REWARD = 4;
const CARD = 5;

class BagOfChips implements BagOfChipsGame {
    public cardsManager: CardsManager;
    public chipsManager: ChipsManager;

    private zoomManager: ZoomManager;
    private animationManager: AnimationManager;
    private gamedatas: BagOfChipsGamedatas;
    private tableCenter: TableCenter;
    private playersTables: PlayerTable[] = [];
    private rewardsCounters: Counter[] = [];
    
    private TOOLTIP_DELAY = document.body.classList.contains('touch-device') ? 1500 : undefined;

    constructor() {
    }
    
    /*
        setup:

        This method must set up the game user interface according to current game situation specified
        in parameters.

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)

        "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
    */

    public setup(gamedatas: BagOfChipsGamedatas) {
        /* TODO if (!gamedatas.variantOption) {
            (this as any).dontPreloadImage('artefacts.jpg');
        }
        if (gamedatas.boatSideOption == 2) {
            (this as any).dontPreloadImage('boats-normal.png');
        } else {
            (this as any).dontPreloadImage('boats-advanced.png');
        }*/

        log( "Starting game setup" );
        
        this.gamedatas = gamedatas;

        log('gamedatas', gamedatas);


        this.cardsManager = new CardsManager(this);
        this.chipsManager = new ChipsManager(this);        
        this.animationManager = new AnimationManager(this);
        new JumpToManager(this, {
            localStorageFoldedKey: LOCAL_STORAGE_JUMP_TO_FOLDED_KEY,
            topEntries: [
                new JumpToEntry(_('Main board'), 'table-center', { 'color': '#224757' })
            ],
            entryClasses: 'round-point',
        });

        this.tableCenter = new TableCenter(this, gamedatas);
        this.createPlayerPanels(gamedatas);
        this.createPlayerTables(gamedatas);
        
        this.zoomManager = new ZoomManager({
            element: document.getElementById('table'),
            smooth: false,
            zoomControls: {
                color: 'black',
            },
            localStorageZoomKey: LOCAL_STORAGE_ZOOM_KEY,
            onDimensionsChange: () => {
                const tablesAndCenter = document.getElementById('tables-and-center');
                const clientWidth = tablesAndCenter.clientWidth;
                tablesAndCenter.classList.toggle('double-column', clientWidth > 2678); // TODO
            },
        });

        new HelpManager(this, { 
            buttons: [
                new BgaHelpPopinButton({
                    title: _("Card help").toUpperCase(),
                    html: this.getHelpHtml(),
                    onPopinCreated: () => this.populateHelp(),
                    buttonBackground: '#5890a9',
                }),
                new BgaHelpExpandableButton({
                    //unfoldedHtml: this.getColorAddHtml(),
                    foldedContentExtraClasses: 'color-help-folded-content',
                    unfoldedContentExtraClasses: 'color-help-unfolded-content',
                    expandedWidth: '120px',
                    expandedHeight: '210px',
                }),
            ]
        });
        this.setupNotifications();
        this.setupPreferences();

        log( "Ending game setup" );
    }

    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    public onEnteringState(stateName: string, args: any) {
        log('Entering state: ' + stateName, args.args);
    }

    private onEnteringSelectCards() {
        if ((this as any).isCurrentPlayerActive()) {
            this.getCurrentPlayerTable().setHandSelectable(true);
        }
    }

    public onLeavingState(stateName: string) {
        log( 'Leaving state: '+stateName );

        switch (stateName) {
            case 'discardCards':
            case 'placeCards':
                this.onLeavingSelectCards();
                break;
        }
    }

    private onLeavingSelectCards() {
        this.getCurrentPlayerTable()?.setHandSelectable(false);
    }

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    public onUpdateActionButtons(stateName: string, args: any) {
        
        if ((this as any).isCurrentPlayerActive()) {
            switch (stateName) {
                case 'discardCards':
                    this.onEnteringSelectCards();
                    (this as any).addActionButton(`discardCards_button`, '', () => this.discardCards());
                    this.onHandCardSelectionChange([]);
                    break;
                case 'placeCards':
                    this.onEnteringSelectCards();
                    (this as any).addActionButton(`placeMinus_button`, '', () => this.placeCards());
                    (this as any).addActionButton(`placePlus_button`, '', () => this.placeCards());
                    this.onHandCardSelectionChange([]);
                    break;
            }
        }
    }    
    
    public onHandCardSelectionChange(selection: Card[]): void {
        if (this.gamedatas.gamestate.name == 'discardCards') {
            const label = _('Discard ${number} selected cards').replace('${number}', `${selection.length}`);

            const button = document.getElementById('discardCards_button');
            button.innerHTML = label;
            button.classList.toggle('disabled', selection.length != +this.gamedatas.gamestate.args.number);
        } else if (this.gamedatas.gamestate.name == 'placeCards') {
            const minusLabel = _('Set selected card on minus side');

            const minusButton = document.getElementById('placeMinus_button');
            minusButton.innerHTML = minusLabel;
            minusButton.classList.toggle('disabled', selection.length != 1);

            const plusLabel = _('Set selected cards on plus side');

            const plusButton = document.getElementById('placePlus_button');
            plusButton.innerHTML = plusLabel;
            plusButton.classList.toggle('disabled', selection.length != 2);
        }
    }

    ///////////////////////////////////////////////////
    //// Utility methods


    ///////////////////////////////////////////////////

    public setTooltip(id: string, html: string) {
        (this as any).addTooltipHtml(id, html, this.TOOLTIP_DELAY);
    }
    public setTooltipToClass(className: string, html: string) {
        (this as any).addTooltipHtmlToClass(className, html, this.TOOLTIP_DELAY);
    }

    public getPlayerId(): number {
        return Number((this as any).player_id);
    }

    public getPlayer(playerId: number): BagOfChipsPlayer {
        return Object.values(this.gamedatas.players).find(player => Number(player.id) == playerId);
    }

    private getPlayerTable(playerId: number): PlayerTable {
        return this.playersTables.find(playerTable => playerTable.playerId === playerId);
    }

    public getCurrentPlayerTable(): PlayerTable | null {
        return this.playersTables.find(playerTable => playerTable.playerId === this.getPlayerId());
    }

    public getGameStateName(): string {
        return this.gamedatas.gamestate.name;
    }

    private setupPreferences() {
        // Extract the ID and value from the UI control
        const onchange = (e) => {
          var match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/);
          if (!match) {
            return;
          }
          var prefId = +match[1];
          var prefValue = +e.target.value;
          (this as any).prefs[prefId].value = prefValue;
        }
        
        // Call onPreferenceChange() when any value changes
        dojo.query(".preference_control").connect("onchange", onchange);
        
        // Call onPreferenceChange() now
        dojo.forEach(
          dojo.query("#ingame_menu_content .preference_control"),
          el => onchange({ target: el })
        );
    }

    private getOrderedPlayers(gamedatas: BagOfChipsGamedatas) {
        const players = Object.values(gamedatas.players).sort((a, b) => a.playerNo - b.playerNo);
        const playerIndex = players.findIndex(player => Number(player.id) === Number((this as any).player_id));
        const orderedPlayers = playerIndex > 0 ? [...players.slice(playerIndex), ...players.slice(0, playerIndex)] : players;
        return orderedPlayers;
    }

    private createPlayerPanels(gamedatas: BagOfChipsGamedatas) {

        Object.values(gamedatas.players).forEach(player => {
            const playerId = Number(player.id);   

            let html = `<div class="counters">
            
                <div id="reward-counter-wrapper-${player.id}" class="reward-counter">
                    <div class="reward icon"></div>
                    <span id="reward-counter-${player.id}"></span>
                </div>

            </div>`;

            dojo.place(html, `player_board_${player.id}`);

            this.rewardsCounters[playerId] = new ebg.counter();
            this.rewardsCounters[playerId].create(`reward-counter-${playerId}`);
            this.rewardsCounters[playerId].setValue(player.rewards);
        });

        this.setTooltipToClass('reward-counter', _('Rewards'));
    }

    private createPlayerTables(gamedatas: BagOfChipsGamedatas) {
        const orderedPlayers = this.getOrderedPlayers(gamedatas);

        orderedPlayers.forEach(player => 
            this.createPlayerTable(gamedatas, Number(player.id))
        );
    }

    private createPlayerTable(gamedatas: BagOfChipsGamedatas, playerId: number) {
        const table = new PlayerTable(this, gamedatas.players[playerId]);
        this.playersTables.push(table);
    }

    private setReward(playerId: number, count: number) {
        this.rewardsCounters[playerId].toValue(count);
    }

    private getHelpHtml() {
        let html = `
        <div id="help-popin">
            <h1>${_("Assets")}</h2>
            <div class="help-section">
                <div class="icon vp"></div>
                <div class="help-label">${_("Gain 1 <strong>Victory Point</strong>. The player moves their token forward 1 space on the Score Track.")}</div>
            </div>
            <div class="help-section">
                <div class="icon recruit"></div>
                <div class="help-label">${_("Gain 1 <strong>Recruit</strong>: The player adds 1 Recruit token to their ship.")} ${_("It is not possible to have more than 3.")} ${_("A recruit allows a player to draw the Viking card of their choice when Recruiting or replaces a Viking card during Exploration.")}</div>
            </div>
            <div class="help-section">
                <div class="icon bracelet"></div>
                <div class="help-label">${_("Gain 1 <strong>Silver Bracelet</strong>: The player adds 1 Silver Bracelet token to their ship.")} ${_("It is not possible to have more than 3.")} ${_("They are used for Trading.")}</div>
            </div>
            <div class="help-section">
                <div class="icon reward"></div>
                <div class="help-label">${_("Gain 1 <strong>Reward Point</strong>: The player moves their token forward 1 space on the Reward Track.")}</div>
            </div>
            <div class="help-section">
                <div class="icon take-card"></div>
                <div class="help-label">${_("Draw <strong>the first Viking card</strong> from the deck: It is placed in the player’s Crew Zone (without taking any assets).")}</div>
            </div>

            <h1>${_("Powers of the artifacts (variant option)")}</h1>
        `;

        for (let i = 1; i <=7; i++) {
            /*html += `
            <div class="help-section">
                <div id="help-artifact-${i}"></div>
                <div>${this.artifactsManager.getTooltip(i)}</div>
            </div> `;*/
        }
        html += `</div>`;

        return html;
    }

    private populateHelp() {
        for (let i = 1; i <=7; i++) {
            //this.artifactsManager.setForHelp(i, `help-artifact-${i}`);
        }
    }
    
    public discardCards() {
        if(!(this as any).checkAction('discardCards')) {
            return;
        }

        const ids = this.getCurrentPlayerTable().hand.getSelection().map(card => card.id);

        this.takeAction('discardCards', {
            ids: ids.join(','),
        });
    }
    
    public placeCards() {
        if(!(this as any).checkAction('placeCards')) {
            return;
        }

        const ids = this.getCurrentPlayerTable().hand.getSelection().map(card => card.id);
        const others = this.getCurrentPlayerTable().hand.getCards().filter(card => !ids.includes(card.id)).map(card => card.id);


        this.takeAction('placeCards', {
            minus: (ids.length == 1 ? ids : others).join(','),
            plus: (ids.length == 2 ? ids : others).join(','),
        });
    }

    public takeAction(action: string, data?: any) {
        data = data || {};
        data.lock = true;
        (this as any).ajaxcall(`/bagofchips/bagofchips/${action}.html`, data, this, () => {});
    }

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    /*
        setupNotifications:

        In this method, you associate each of your game notifications with your local method to handle it.

        Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                your pylos.game.php file.

    */
    setupNotifications() {
        //log( 'notifications subscriptions setup' );

        const notifs = [
            ['discardCards', undefined],
            ['placeCards', undefined],
            ['newHand', undefined],
            ['revealChips', undefined],
            ['endTurn', ANIMATION_MS],
        ];
    
        notifs.forEach((notif) => {
            dojo.subscribe(notif[0], this, (notifDetails: Notif<any>) => {
                log(`notif_${notif[0]}`, notifDetails.args);

                const promise = this[`notif_${notif[0]}`](notifDetails.args);

                // tell the UI notification ends, if the function returned a promise
                promise?.then(() => (this as any).notifqueue.onSynchronousNotificationEnd());
            });
            (this as any).notifqueue.setSynchronous(notif[0], notif[1]);
        });

        if (isDebug) {
            notifs.forEach((notif) => {
                if (!this[`notif_${notif[0]}`]) {
                    console.warn(`notif_${notif[0]} function is not declared, but listed in setupNotifications`);
                }
            });

            Object.getOwnPropertyNames(BagOfChips.prototype).filter(item => item.startsWith('notif_')).map(item => item.slice(6)).forEach(item => {
                if (!notifs.some(notif => notif[0] == item)) {
                    console.warn(`notif_${item} function is declared, but not listed in setupNotifications`);
                }
            });
        }
    }

    notif_discardCards(args: NotifDiscardCardsArgs) {
        return this.getPlayerTable(args.playerId).discardCards(args.discard);
    }

    notif_placeCards(args: NotifPlaceCardsArgs) {
        return this.getPlayerTable(args.playerId).placeCards(args.minus, args.plus);
    }

    notif_newHand(args: NotifNewHandArgs) {
        return this.getCurrentPlayerTable().newHand(args.cards);
    }

    notif_revealChips(args: NotifRevealChipsArgs) {
        return this.tableCenter.revealChips(args.slot, args.chips);
    }

    notif_endTurn() {
        // TODO clean all
    }
    

    /* This enable to inject translatable styled things to logs or action bar */
    /* @Override */
    public format_string_recursive(log: string, args: any) {
        try {
            if (log && args && !args.processed) {
                if (args.gains && (typeof args.gains !== 'string' || args.gains[0] !== '<')) {
                    const entries = Object.entries(args.gains);
                    args.gains = entries.length ? entries.map(entry => `<strong>${entry[1]}</strong> <div class="icon" data-type="${entry[0]}"></div>`).join(' ') : `<strong>${_('nothing')}</strong>`;
                }

                for (const property in args) {
                    if (['number', 'color', 'card_color', 'card_type', 'artifact_name'].includes(property) && args[property][0] != '<') {
                        args[property] = `<strong>${_(args[property])}</strong>`;
                    }
                }
            }
        } catch (e) {
            console.error(log,args,"Exception thrown", e.stack);
        }
        return (this as any).inherited(arguments);
    }
}