/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BriscolaSuperamici implementation : © Tommaso Pifferi <p.tommy93@gmail.com> & Antonio <ai6chr+briscola@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * briscolasuperamici.js
 *
 * BriscolaSuperamici user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter",
    "ebg/stock"
],
function (dojo, declare) {
    return declare("bgagame.briscolasuperamici", ebg.core.gamegui, {
        constructor: function(){
            // Declare cards width/height for various decks type
            this.italianDeckId = 1;
            this.italianCardWidth = 72;
            this.italianCardHeight = 123;
            this.italianCardsImage = 'img/italian-cards.jpg';

            this.frenchDeckId = 2;
            this.frenchCardWidth = 72;
            this.frenchCardHeight = 96;
            this.frenchCardsImage = 'img/french-cards.jpg';
        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup: function(gamedatas)
        {
            var self = this;

            // Use italian deck as default
            this.deckId = this.italianDeckId;
            this.deckType = 'italian';

            $('current_style').innerHTML = _('Italian deck');

            this.cardwidth = this.italianCardWidth;
            this.cardheight = this.italianCardHeight;
            this.cardsImage = this.italianCardsImage;

            // Add right class to player table
            dojo.query('.playertable').addClass('italian');
            dojo.query('.playertablecard').addClass('italian');

            // Adjust deck size to fit italian cards
            dojo.addClass('mydeck_wrap', 'italian');
            dojo.addClass('briscola_wrap', 'italian');

            var playersCount = 0;
            for (var pId in gamedatas.players) {
                if (gamedatas.players.hasOwnProperty(pId)) {
                    playersCount++;

                    // Set up player boards
                    var player = gamedatas.players[pId];

                    // Set up players boards if needed
                    var playerBoardDiv = $('player_board_' + pId);
                    dojo.place(this.format_block('jstpl_player_board', player), playerBoardDiv);

                    var div = $('trickscount_p' + pId);
                    if (div) {
                        div.innerHTML = player['tricks'];
                    }
                }
            }
            this.addTooltipToClass("tricks_icon", _("Tricks won during this hand"), '');

            // Player hand
            this.playerHand = this.createDeck('myhand', false);
            // Set up card handlers
            dojo.connect(this.playerHand, 'onChangeSelection', this, 'onPlayerHandSelectionChanged');

            // Create a custom deck just to hold briscola
            this.briscolaCard = this.createDeck('briscola_wrap', false);

            // Cards in player's hand
            for (var i in this.gamedatas.hand) {
                var card = this.gamedatas.hand[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            // Cards played on table
            for (var j in this.gamedatas.cardsontable) {
                var card = this.gamedatas.cardsontable[j];
                var color = card.type;
                var value = card.type_arg;
                var playerId = card.location_arg;
                this.playCardOnTable(playerId, color, value, card.id);
            }

            // Show deck on the table
            this.buildDeckOnTable(gamedatas);
            this.addTooltip('mydeck', _('Deck with cards to be drawn'), '');

            // Add tooltips
            this.addTooltipToClass('playertablecard', _("Card played on the table"), '');
            this.addTooltip('briscola_wrap', _("Briscola card (to be drawn when the deck is empty)"), '');

            // Hide orientation icon if 2 players only
            if (playersCount === 2) {
                dojo.style('orientation', 'display', 'none');
            }

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            // Interpolate logs
            var logsIntepolatorFn = setInterval(function() {
                var cardValues = dojo.query('.briscola-logs-card-value');
                var cardSuits = dojo.query('.briscola-logs-card-suit');
                if (cardValues.length > 0 || cardSuits.length > 0) {
                    clearInterval(logsIntepolatorFn);

                    self.interpolateLogs();
                }
            }, 100);

            // Add listener for deck style change
            dojo
                .query('.change-deck-style')
                .connect('onclick', this, 'onClickChangeDeckStyleButton');

            // Add another listener when a user change his preferences
            dojo.connect($('preference_control_100'), 'onchange', this, 'onChangeUserPreferenceDeckStyle');

            // Now check if user preference regarding deck style is different
            // fro the one we assumed (italian).
            // N.B: We need to do this now because decks and all the other
            // structures have been created
            var userDeckPreference = this.prefs[100].value;
            if (userDeckPreference == this.frenchDeckId) {
                this.changeDeckStyle();
            }

            // Add dealer
            // N.B: We do this now, after possibly changing deck style
            // due to the fact that it's an animation and it needs the
            // correct information in order to display the icon correctly
            this.setDealer(gamedatas.dealer);
            this.addTooltip('dealer_icon', _('Dealer for this hand'), '');
        },
       

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function(stateName, args) {
            console.log('Entering state: ' + stateName);

            switch(stateName) {
                case 'showCards':
                    console.log('I am now in showCards state');
                    console.log(args);
                    break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function(stateName) {
            console.log('Leaving state: ' + stateName);
            
            switch(stateName) {
                case 'dummmy':
                    break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function(stateName, args) {
            console.log('onUpdateActionButtons: ' + stateName);
                      
            if(this.isCurrentPlayerActive()) {
                switch(stateName) {
                    case 'showCards':
                        this.teammateHand = args._private.teammate_hand;
                        this.teamWonTricks = args._private.trickswon;

                        this.addActionButton('showTeammateCards_button', _('Show teammate cards'), 'onClickShowTeammateCards');
                        this.addActionButton('showTeamTricks_button', _('Show all tricks won by your team'), 'onClickShowTeamWonTricks');
                        this.addActionButton('showGoToNextTurn_button', _('Go to next turn'), 'onClickGoToNextTurn');

                        break;
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods

        createDeck: function(domId, weighted) {
            var deckObject = new ebg.stock();
            deckObject.create(this, $(domId), this.cardwidth, this.cardheight);
            deckObject.image_items_per_row = 10;

            // Create cards types
            for (var color = 1; color <= 4; color++) {
                for (var value = 2; value <= 11; value++) {
                    // Build card type id
                    var cardTypeId = this.getCardUniqueId(color, value);

                    if (weighted) {
                        // Assign card id as weight, so that they appear sorted inside a deck
                        deckObject.addItemType(cardTypeId, cardTypeId, g_gamethemeurl + this.cardsImage, cardTypeId);
                    } else {
                        // Cards are not sorted, order is random (used e.g. in player's hand)
                        deckObject.addItemType(cardTypeId, 0, g_gamethemeurl + this.cardsImage, cardTypeId);
                    }
                }
            }

            // Add border for italian cards
            if (this.deckType === 'italian') {
                deckObject.onItemCreate = dojo.hitch(this, 'addBorder');
            }

            return deckObject;
        },

        getCardUniqueId : function(color, value) {
            return (color - 1) * 10 + (value - 2);
        },

        playCardOnTable : function(playerId, color, value, cardId) {
            // player_id => direction
            dojo.place(this.format_block('jstpl_cardontable', {
                x : this.cardwidth * (value - 2),
                y : this.cardheight * (color - 1),
                value: value,
                color: color,
                player_id : playerId,
                deck_type: this.deckType
            }), 'playertablecard_' + playerId);

            if (playerId != this.player_id) {
                // Some opponent played a card
                // Move card from player panel
                this.placeOnObject('cardontable_' + playerId, 'overall_player_board_' + playerId);
            } else {
                // You played a card. If it exists in your hand, move card from there and remove corresponding item
                if ($('myhand_item_' + cardId)) {
                    this.placeOnObject('cardontable_' + playerId, 'myhand_item_' + cardId);
                    this.playerHand.removeFromStockById(cardId);
                }
            }

            // In any case: move it to its final destination
            this.slideToObject('cardontable_' + playerId, 'playertablecard_' + playerId).play();
        },

        buildDeckOnTable: function(data) {
            if (data.cardsindeck) {
                // Create single deck on table
                dojo.place(this.format_block('jstpl_mydeck'), 'mydeck_wrap');

                // Add deck's remaining cards label
                dojo.place(this.format_block('jstpl_remaining_cards', {
                    remainingcards: data.cardsindeck
                }), 'remainingcards_wrap');

                // Cosmetic
                if (data.cardsindeck < 10) {
                    dojo.addClass('remainingcards_wrap', 'smaller');
                } else {
                    dojo.removeClass('remainingcards_wrap', 'smaller');
                }
            }

            // Finally, add briscola if present
            var briscola = data.briscola;
            if (briscola) {
                var color = briscola.type;
                var value = briscola.type_arg;
                this.briscolaCard.addToStockWithId(this.getCardUniqueId(color, value), briscola.id);
            }
        },

        setDealer: function(playerId) {
            // Slide into position (bottom right of this player play zone)
            if (this.deckType === 'italian') {
                this.slideToObjectPos('dealer_icon', 'playertablecard_' + playerId, 78, 100, 1000).play();
            } else if (this.deckType === 'french') {
                this.slideToObjectPos('dealer_icon', 'playertablecard_' + playerId, 78, 78, 1000).play();
            }

        },

        isMobile: function() {
            var check = false;
            (function(a){if(/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4))) check = true;})(navigator.userAgent||navigator.vendor||window.opera);
            return check;
        },

        addBorder: function(card_div) {
            dojo.addClass(card_div, 'bordered-card');
        },

        interpolateLogs: function() {
            if (this.deckType === 'italian') {
                // Show italian logs
                dojo.query('.briscola-logs-card-value.french').addClass('display-none');
                dojo.query('.briscola-logs-card-suit.french').addClass('display-none');

                dojo.query('.briscola-logs-card-value.italian').removeClass('display-none');
                dojo.query('.briscola-logs-card-suit.italian').removeClass('display-none');
            } else {
                // Show french logs
                dojo.query('.briscola-logs-card-value.italian').addClass('display-none');
                dojo.query('.briscola-logs-card-suit.italian').addClass('display-none');

                dojo.query('.briscola-logs-card-value.french').removeClass('display-none');
                dojo.query('.briscola-logs-card-suit.french').removeClass('display-none');
            }
        },

        ///////////////////////////////////////////////////
        //// Player's action

        onPlayerHandSelectionChanged : function() {
            var items = this.playerHand.getSelectedItems();

            if (items.length > 0) {
                var action = 'playCard';
                if (this.checkAction(action)) {
                    // Can play a card
                    var cardId = items[0].id;
                    this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", {
                        id : cardId,
                        lock : true
                    }, this, function(result) {
                    }, function(is_error) {
                    });

                    this.playerHand.unselectAll();
                } else {
                    this.playerHand.unselectAll();
                }
            }
        },

        onClickShowTeammateCards: function() {
            // Prepare dialog to show cards
            var teammateCardsDialog = new dijit.Dialog({
                title: _("Your teammate hand"),
                style: 'width: 250px',
                onCancel: function() {
                    dojo.destroy('teammate-hand');
                }
            });
            // Add a global property to programmatically show/hide the dialog
            this.teammateCardsDialog = teammateCardsDialog;

            var html = '<div id="teammate-hand"></div>';
            teammateCardsDialog.attr("content", html);
            teammateCardsDialog.show();

            // Build temporary deck and add cards do it
            var teammateCardsDeck = this.createDeck('teammate-hand', true);
            for (var i in this.teammateHand) {
                var card = this.teammateHand[i];
                var color = card.type;
                var value = card.type_arg;
                teammateCardsDeck.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }
        },

        onClickShowTeamWonTricks: function() {
            // Prepare dialog to show cards
            var teamTricksWonDialog = new dijit.Dialog({
                title: _("Tricks won by your team"),
                style: this.isMobile() ? 'width: 250px' : 'width: 550px',
                onCancel: function() {
                    dojo.destroy('team-tricks-won');
                }
            });
            // Add a global property to programmatically show/hide the dialog
            this.teamTricksWonDialog = teamTricksWonDialog;

            var html = '<div id="team-tricks-won"></div>';
            teamTricksWonDialog.attr("content", html);
            teamTricksWonDialog.show();

            // Build temporary deck and add cards do it
            var teamTricksWonDeck = this.createDeck('team-tricks-won', true);
            for (var i in this.teamWonTricks) {
                var card = this.teamWonTricks[i];
                var color = card.type;
                var value = card.type_arg;
                teamTricksWonDeck.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }
        },

        onClickGoToNextTurn: function() {
            var action = 'endShowCards';
            if (this.checkAction(action)) {
                this.ajaxcall("/" + this.game_name + "/" + this.game_name + "/" + action + ".html", {
                    lock : true
                }, this, function(result) {
                }, function(is_error) {
                });
            }
        },

        onClickChangeDeckStyleButton: function() {
            // This event is triggered from the button on the bottom-right
            // part of the table, so we need to change the deck as well
            // as user preference.
            // We do so by triggering a `onchange` event on the user prefs select
            // Get the next style id in the list
            if (this.deckId === this.italianDeckId) {
                var newDeckId = this.frenchDeckId;
            } else {
                var newDeckId = this.italianDeckId;
            }

            // Set that new style as the player preference
            dojo.query('#preference_control_100 > option[value="' + newDeckId + '"], #preference_fontrol_100 > option[value="' + newDeckId + '"]').forEach(function(node) {
                dojo.attr(node, 'selected', true);
            });

            // Trigger the onchange event to effectively change the style
            var select = $('preference_control_100');
            // IE does things differently
            if (dojo.isIE) {
                select.fireEvent("onchange");
            }
            else{ // Not IE
                var event = document.createEvent("HTMLEvents");
                event.initEvent("change", false, true);
                select.dispatchEvent(event);
            }
        },

        onChangeUserPreferenceDeckStyle: function(event) {
            var select = event.currentTarget;
            var newDeckId = select.options[select.selectedIndex].value;

            // Don't do anything if the option is not actually changed
            if (newDeckId == this.deckId) {
                return;
            }

            // Set that new style as the player preference
            dojo.query('#preference_control_100 > option[value="' + newDeckId + '"], #preference_fontrol_100 > option[value="' + newDeckId + '"]').forEach(function(node) {
                dojo.attr(node, 'selected', true);
            });

            // Change style of cards on table
            this.changeDeckStyle();
        },

        changeDeckStyle: function() {
            var self = this;

            // Check if one of the modals is opened and close it
            // to prevent graphical overlapping of cards
            if (this.teamTricksWonDialog && this.teamTricksWonDialog.open) {
                this.teamTricksWonDialog.destroy();
            }

            if (this.teammateCardsDialog && this.teammateCardsDialog.open) {
                this.teammateCardsDialog.destroy();
            }

            // Please note that everything inside one `if` branch needs to
            // be copied inside all the other branches (it's just a big
            // DOM manipulation mess)
            if (this.deckType === 'italian') {
                // Switch to french
                this.deckId = this.frenchDeckId;
                this.deckType = 'french';

                $('current_style').innerHTML = _('French deck');

                this.cardwidth = this.frenchCardWidth;
                this.cardheight = this.frenchCardHeight;
                this.cardsImage = this.frenchCardsImage;

                // Add right class to player table
                dojo.query('.playertable').removeClass('italian').addClass('french');
                dojo.query('.playertablecard').removeClass('italian').addClass('french');

                // Adjust deck size to remove fit for italian cards
                dojo.removeClass('mydeck_wrap', 'italian');
                dojo.addClass('mydeck_wrap', 'french');
                dojo.removeClass('briscola_wrap', 'italian');
                dojo.addClass('briscola_wrap', 'french');

                // Replace deck images
                var decks = [this.playerHand, this.briscolaCard];
                var image = null;
                for (var i in decks) {
                    var deck = decks[i];
                    deck.item_height = this.cardheight;
                    deck.item_width = this.cardwidth;

                    for (var j in deck.item_type) {
                        var item = deck.item_type[j];

                        if (!image) {
                            image = item.image.replace(this.italianCardsImage, this.frenchCardsImage);
                        }

                        item.image = image;
                    }

                    // Remove border on new cards
                    deck.onItemCreate = null;

                    deck.updateDisplay();
                }

                // Change style of the current visible cards in the stocks
                dojo.query('.stockitem').style({
                    'background-image': 'url(' + image + ')',
                    height: this.cardheight + 'px',
                    width: this.cardwidth + 'px'
                });
                // Remove border from existing cards
                dojo.query('.stockitem').removeClass('bordered-card');

                // Change class of the cards on the table
                dojo.query('.cardontable').removeClass('italian');
                dojo.query('.cardontable').addClass('french');
                // Change background-position attribute of cards in table,
                // since they are not inside a deck!
                // N.B: The formula is the same used in playCardOnTable
                dojo.query('.cardontable').forEach(function(element) {
                    var itemX = self.cardwidth * (parseInt(element.getAttribute('data-value')) - 2);
                    var backgroundPositionX = '-' + itemX + 'px';

                    var itemY = self.cardheight * (element.getAttribute('data-color') - 1);
                    var backgroundPositionY = '-' + itemY + 'px';

                    element.style.backgroundPosition = backgroundPositionX + ' ' + backgroundPositionY;
                });

                // Move the dealer icon (14px is the tableplayer delta between french/italian)
                var dealerPosition = dojo.getStyle('dealer_icon', 'top');
                var newDealerPosition = parseFloat(dealerPosition) - 14;
                dojo.setStyle('dealer_icon', 'top', newDealerPosition + 'px');

                // Move the change deck box up
                dojo.setStyle('change_deck_style_wrapper', 'top', 62 + 'px');
            } else {
                // Switch to italian
                this.deckId = this.italianDeckId;
                this.deckType = 'italian';

                $('current_style').innerHTML = _('Italian deck');

                this.cardwidth = this.italianCardWidth;
                this.cardheight = this.italianCardHeight;
                this.cardsImage = this.italianCardsImage;

                // Add right class to player table
                dojo.query('.playertable').removeClass('french').addClass('italian');
                dojo.query('.playertablecard').removeClass('french').addClass('italian');

                // Adjust deck size to fit italian cards
                dojo.removeClass('mydeck_wrap', 'french');
                dojo.addClass('mydeck_wrap', 'italian');
                dojo.removeClass('briscola_wrap', 'french');
                dojo.addClass('briscola_wrap', 'italian');

                // Replace deck images
                var decks = [this.playerHand, this.briscolaCard];
                var image = null;
                for (var i in decks) {
                    var deck = decks[i];
                    deck.item_height = this.cardheight;
                    deck.item_width = this.cardwidth;

                    for (var j in deck.item_type) {
                        var item = deck.item_type[j];

                        if (!image) {
                            image = item.image.replace(this.frenchCardsImage, this.italianCardsImage);
                        }

                        item.image = image;
                    }

                    // Add border on new cards
                    deck.onItemCreate = dojo.hitch(this, 'addBorder');

                    deck.updateDisplay();
                }

                // Change style of the current visible cards in the stocks
                dojo.query('.stockitem').style({
                    'background-image': 'url(' + image + ')',
                    height: this.cardheight + 'px',
                    width: this.cardwidth + 'px'
                });
                // Add border to existing cards
                dojo.query('.stockitem').addClass('bordered-card');

                // Change class of the cards on the table
                dojo.query('.cardontable').removeClass('french');
                dojo.query('.cardontable').addClass('italian');
                // Change background-position attribute of cards in table,
                // since they are not inside a deck!
                // N.B: The formula is the same used in playCardOnTable
                dojo.query('.cardontable').forEach(function(element) {
                    var itemX = self.cardwidth * (parseInt(element.getAttribute('data-value')) - 2);
                    var backgroundPositionX = '-' + itemX + 'px';

                    var itemY = self.cardheight * (element.getAttribute('data-color') - 1);
                    var backgroundPositionY = '-' + itemY + 'px';

                    element.style.backgroundPosition = backgroundPositionX + ' ' + backgroundPositionY;
                });

                // Move the dealer icon (14px is the tableplayer delta between french/italian)
                var dealerPosition = dojo.getStyle('dealer_icon', 'top');
                var newDealerPosition = parseFloat(dealerPosition) + 14;
                dojo.setStyle('dealer_icon', 'top', newDealerPosition + 'px');

                // Move the change deck box up
                dojo.setStyle('change_deck_style_wrapper', 'top', 88 + 'px');
            }

            // Transform logs as well
            // N.B: This has to be called after new deckType has been declared
            this.interpolateLogs();
        },
        
        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your briscolasuperamici.game.php file.
        
        */
        setupNotifications: function()
        {
            dojo.subscribe('dealCards', this, "notif_dealCards");
            dojo.subscribe('newHand', this, 'notif_newHand');
            dojo.subscribe('playCard', this, 'notif_playCard');

            dojo.subscribe('trickWin', this, 'notif_trickWin');
            dojo.subscribe('giveAllCardsToPlayer', this, 'notif_giveAllCardsToPlayer');
            dojo.subscribe('drawNewCard', this, 'notif_drawNewCard');

            dojo.subscribe('newScores', this, 'notif_newScores');

            this.notifqueue.setSynchronous('newScores', 2000);
            this.notifqueue.setSynchronous('trickWin', 1200);
            this.notifqueue.setSynchronous('giveAllCardsToPlayer', 1300);
        },

        notif_dealCards: function(notif) {
            // Redundant, show the cards dealer
            this.setDealer(notif.args.player_id);
        },

        notif_newHand : function(notif) {
            // We received a new full hand of 3 cards.
            this.playerHand.removeAll();
            this.briscolaCard.removeAll();

            for (var i in notif.args.cards) {
                var card = notif.args.cards[i];
                var color = card.type;
                var value = card.type_arg;
                this.playerHand.addToStockWithId(this.getCardUniqueId(color, value), card.id);
            }

            // Show deck on table
            this.buildDeckOnTable(notif.args);
            this.addTooltip('mydeck', _('Deck with cards to be drawn'), '');
        },

        notif_playCard : function(notif) {
            // Play a card on the table
            this.playCardOnTable(notif.args.player_id, notif.args.color, notif.args.value, notif.args.card_id);

            this.interpolateLogs();
        },

        notif_trickWin : function(notif) {
            var div = $('trickscount_p' + notif.args.player_id);
            if (div) {
                div.innerHTML = notif.args.tricks;
            }
        },

        notif_giveAllCardsToPlayer : function(notif) {
            // Move all cards on table to player table, then destroy them
            var winnerId = notif.args.player_id;
            for (var playerId in this.gamedatas.players) {
                var anim = this.slideToObject('cardontable_' + playerId, 'playertablecard_' + winnerId, 700, 0);

                dojo.connect(anim, 'onEnd', this, 'fadeOutAndDestroy');

                anim.play();
            }
        },

        notif_drawNewCard: function(notif) {
            var self = this;

            // Variables about the card's draw animation
            var cardIndexToPick = notif.args.card_index_to_pick;
            var remainingCards = notif.args.remaining_cards;

            // Variables about the drawn card itself
            var card = notif.args.card;
            var color = card.type;
            var value = card.type_arg;

            var idBriscola = notif.args.id_briscola;

            if (cardIndexToPick == 0) {
                // If deck_index_to_pick is 0, the player needs to pick the briscola,
                // and everything deck related must be destroyed
                dojo.destroy('mydeck');
                dojo.destroy('remainingcards');
                var anim = this.slideToObject('briscola_wrap_item_' + idBriscola, 'myhand');
            } else {
                // Otherwise, a new temp deck is created just for the sake of animation
                var deckDomNode = $('mydeck');
                var deckTemp = dojo.clone(deckDomNode);
                dojo.attr(deckTemp, "id", "mydeck-temp");
                dojo.place(deckTemp, 'mydeck');

                var anim = this.slideToObject('mydeck-temp', 'myhand');
            }

            dojo.connect(anim, 'onEnd', function(node) {
                dojo.destroy(node);

                dojo.destroy('remainingcards');
                if (remainingCards > 0) {
                    // Just update the label with deck count
                    dojo.place(self.format_block('jstpl_remaining_cards', {
                        remainingcards: remainingCards
                    }), 'remainingcards_wrap');

                    // Cosmetic
                    if (remainingCards < 10) {
                        dojo.addClass('remainingcards_wrap', 'smaller');
                    } else {
                        dojo.removeClass('remainingcards_wrap', 'smaller');
                    }
                } else {
                    // Destroy deck and empty briscola deck
                    dojo.destroy('mydeck');

                    self.briscolaCard.removeAll();

                    // Remove tooltips
                    self.removeTooltip('mydeck');
                }

                self.playerHand.addToStockWithId(self.getCardUniqueId(color, value), card.id);
            });

            anim.play();
        },

        notif_newScores : function(notif) {
            // Update players' scores
            for (var playerId in notif.args.newScores) {
                this.scoreCtrl[playerId].toValue(notif.args.newScores[playerId]);
            }
        },
   });             
});
