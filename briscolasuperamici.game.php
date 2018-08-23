<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * BriscolaSuperamici implementation : © Tommaso Pifferi <p.tommy93@gmail.com> & Antonio <ai6chr+briscola@gmail.com>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  * 
  * briscolasuperamici.game.php
  *
  * This is the main file for your game logic.
  *
  * In this PHP file, you are going to defines the rules of the game.
  *
  */


require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );

// Local constants
//  - Number of rounds
//  - Team pairing options
//  - Various constants
define("SHORT_GAME", 1); // Short (1 win)
define("CLASSIC_GAME", 2); // Classic (3 wins)
define("LONG_GAME", 3); // Long (5 wins)

define("TEAM_RANDOM", 1); // At random
define("TEAM_1_3", 2); // By table order (1rst/3rd versus 2nd/4th)
define("TEAM_1_2", 3); // By table order (1rst/2nd versus 3rd/4th)
define("TEAM_1_4", 4); // By table order (1rst/4th versus 2nd/3rd)

// Assign a big negative value to briscola card, so that it's drawn as last card
define("BRISCOLA_LOCATION_ARG", -1000);

define("DRAW_SCORE", 60);
define("MINIMUM_WINNING_SCORE", 61);
define("NUMBER_OF_CARDS_IN_FULL_DECK", 40);
define("BIG_SCORE", 100);
define("PERFECT_SCORE", 120);
define("ALL_BRISCOLA_CARDS", 10);

class BriscolaSuperamici extends Table
{
	function __construct( )
	{
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        self::initGameStateLabels(array(
            "firstPlayedColor" => 10,
            "briscolaColor" => 11,
            "briscolaValue" => 12,
            "briscolaCardId" => 13,
            "handNumber" => 14,
            "dealer" => 15,
            "firstPlayer" => 16,
            "showCardsPhaseDone" => 17,
            "winningHandsToEndGame" => 18,
            "roundsNumber" => 100,
            "playersTeams" => 101
        ));

        // Init $this->cards to be a deck
        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");
	}
	
    protected function getGameName( )
    {
		// Used for translations and stuff. Please do not modify.
        return "briscolasuperamici";
    }	

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame( $players, $options = array() )
    {    
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the gams
        $default_colors = array(
            "ff0000",
            "0000ff",
            "ff0000",
            "0000ff"
        );
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_no, player_color, player_canal, player_name, player_avatar) VALUES ";

        // Create some kind of sorted arrays with positions (?) using the undocumented `player_table_order` column
        $order_values = array();
        foreach($players as $player_id => $player) {
            $order_values[] = $player["player_table_order"];
        }

        sort($order_values);
        $position = array();
        foreach($order_values as $key => $val) {
            $position[$val] = $key + 1;
        }

        // Compute player table order based on teams
        $counter = 0;
        $random_dealer = mt_rand(1, count($players));
        $values = array();
        foreach($players as $player_id => $player)
        {
            $color = null;
            $player_no = null;
            $counter++;

            $playersTeams = self::getGameStateValue('playersTeams');
            if ($playersTeams == TEAM_RANDOM) {
                // Random since the $players order is random
                // N.B: 2-players match can only end up here!
                $color = array_shift($default_colors);
                $player_no = $counter;
            } else if (isset($player["player_table_order"])) {
                // By default TEAM_1_3
                $table_order = $position[$player["player_table_order"]];

                if ($playersTeams == TEAM_1_2) {
                    // If TEAM_1_2 swap 2 and 3
                    $table_order = ($table_order == 2 ? 3 : ($table_order == 3 ? 2 : $table_order));
                } else if ($playersTeams == TEAM_1_4) {
                    // If TEAM_1_4 swap 4 and 3
                    $table_order = ($table_order == 3 ? 4 : ($table_order == 4 ? 3 : $table_order));
                }

                if (isset($default_colors[$table_order - 1])) {
                    $color = $default_colors[$table_order - 1];

                    // Adjust player_no for randomizing first player (dealer)
                    if ($table_order >= $random_dealer) {
                        $player_no = $table_order - $random_dealer + 1;
                    } else {
                        $player_no = 4 - ($random_dealer - $table_order) + 1;
                    }
                }
            }

            $values[] = "('" . $player_id . "','" . $player_no . "','$color','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
        }

        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        self::setGameStateInitialValue('firstPlayedColor', 0);
        self::setGameStateInitialValue('briscolaColor', 0);
        self::setGameStateInitialValue('briscolaValue', 0);
        self::setGameStateInitialValue('briscolaCardId', 0);
        self::setGameStateInitialValue('handNumber', 0);
        self::setGameStateInitialValue('dealer', -1);

        $roundsNumberOption = self::getGameStateValue('roundsNumber');
        $roundsNumber = null;
        if ($roundsNumberOption == SHORT_GAME) {
            $roundsNumber = 1;
        } else if ($roundsNumberOption == CLASSIC_GAME) {
            $roundsNumber = 3;
        } else if ($roundsNumberOption == LONG_GAME) {
            $roundsNumber = 5;
        }
        self::setGameStateInitialValue('winningHandsToEndGame', $roundsNumber);

        // Init game statistics
        self::initStat("table", "handNbr", 0);
        self::initStat("player", "bigScore", 0);
        self::initStat("player", "perfectScore", 0);
        self::initStat("player", "noBriscola", 0);
        self::initStat("player", "allBriscola", 0);

        // Create cards
        $cards = array ();
        foreach ( $this->colors as $colorId => $color ) {
            // spade, heart, diamond, club
            // TODO: Change back to 11
            for ($value = 2; $value <= 6; $value ++) {
                //  2, 4, 5 ... K, 3, A
                $cards [] = array ('type' => $colorId,'type_arg' => $value,'nbr' => 1);
            }
        }

        // Create cards deck
        $this->cards->createCards( $cards, 'deck');

        // Activate next player and set first player global
        $this->activeNextPlayer();
        $firstPlayer = self::getActivePlayerId();
        self::setGameStateInitialValue('firstPlayer', $firstPlayer);

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = array();

        // !! We must only return informations visible by this player !!
        $currentPlayerId = self::getCurrentPlayerId();
    
        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb($sql);

        // Gather all information about current game situation (visible by player $current_player_id).

        // Cards in player hand
        $result['hand'] = $this->cards->getCardsInLocation('hand', $currentPlayerId);

        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation('cardsontable');

        $cardsInDeck = $this->cards->getCardsInLocation('deck');
        if (count($cardsInDeck) > 0) {
            $result['cardsindeck'] = count($cardsInDeck) - 1;

            $idCartaBriscola = self::getGameStateValue('briscolaCardId');
            foreach ( $cardsInDeck as $cardId => $card ) {
                if ($cardId == $idCartaBriscola) {
                    $result['briscola'] = $card;
                    break;
                }
            }

        } else {
            $result['cardsindeck'] = 0;
            $result['briscola'] = null;
        }

        // Dealer info
        $result['dealer'] = self::getGameStateValue('dealer');

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        $winningHandsToEndGame = self::getGameStateValue('winningHandsToEndGame');
        $currentCardsInDeck = $this->cards->countCardsInLocation('deck');
        $drawnCardRatio = (NUMBER_OF_CARDS_IN_FULL_DECK - $currentCardsInDeck) / (float) NUMBER_OF_CARDS_IN_FULL_DECK;

        $userScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true );
        $maxScore = 0;
        foreach( $userScores as $playerId => $score ) {
            if ($score > $maxScore) {
                $maxScore = $score;
            }
        }

        $base = 1 / (float) $winningHandsToEndGame * ($maxScore) * 100;
        $current = $drawnCardRatio * 100 / (float) $winningHandsToEndGame;
        $progress = $base + $current;

        return $progress;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */

    function getPlayersToDirection()
    {
        $result = array();

        $players = self::loadPlayersBasicInfos();
        $nextPlayer = self::createNextPlayerTable(array_keys($players));

        $currentPlayer = self::getCurrentPlayerId();

        if (count($players) == 2) {
            $directions = array('S', 'N');
        } else if (count($players) == 4) {
            // Counterclockwise
            $directions = array('S', 'E', 'N', 'W');
        }


        if(!isset($nextPlayer[$currentPlayer])) {
            // Spectator mode: take any player for south
            $player_id = $nextPlayer[0];
            $result[$player_id] = array_shift($directions);
        } else {
            // Normal mode: current player is on south
            $player_id = $currentPlayer;
            $result[$player_id] = array_shift($directions);
        }

        while(count($directions) > 0) {
            $player_id = $nextPlayer[$player_id];
            $result[$player_id] = array_shift($directions);
        }

        return $result;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in briscolasuperamici.action.php)
    */

    function playCard($cardId, $isZombie) {
        self::checkAction("playCard");

        $playerId = self::getActivePlayerId();
        // Check if card is in actual player's hand
        $cardIsInHand = false;
        $playerHand = $this->cards->getCardsInLocation('hand', $playerId);
        foreach($playerHand as $playerCard) {
            if ($playerCard['id'] == $cardId) {
                $cardIsInHand = true;
                break;
            }
        }
        if (!$cardIsInHand) {
            throw new feException("This card is not in your hand");
        }

        $this->cards->moveCard($cardId, 'cardsontable', $playerId);

        $currentCard = $this->cards->getCard($cardId);
        $currentTrickColor = self::getGameStateValue('firstPlayedColor') ;
        if( $currentTrickColor == 0 ) {
            self::setGameStateValue('firstPlayedColor', $currentCard['type'] );
        }

        $message = clienttranslate('${player_name} plays ${value_displayed} ${color_displayed}');
        if ($isZombie) {
            $message = clienttranslate('${player_name} automatically plays ${value_displayed} ${color_displayed}');
        }

        // And notify
        self::notifyAllPlayers('playCard', $message,
            array (
            'i18n' => array ('color_displayed','value_displayed' ),
            'card_id' => $cardId,
            'player_id' => $playerId,
            'player_name' => self::getActivePlayerName(),
            'value' => $currentCard['type_arg'],
            'value_displayed' => $this->values_label[$currentCard['type_arg']],
            'color' => $currentCard['type'],
            'color_displayed' => $this->icons[$currentCard['type']]
            ));

        $this->gamestate->nextState('playCard');
    }

    function endShowCards() {
        self::checkAction("endShowCards");

        // Here we have to get CURRENT player (= player who send the request) and not
        // active player, because we are in a multiple active player state and the "active player"
        // corresponds to nothing.
        $player_id = self::getCurrentPlayerId();

        // Continue the game until the end
        $this->gamestate->setPlayerNonMultiactive($player_id, "endShowCards");
    }
    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    function argShowCards() {
        // Send each team all their won tricks, as well as teammate current hand
        $players = self::loadPlayersBasicInfos();

        $nextPlayer = self::createNextPlayerTable(array_keys($players));
        $firstPlayerId = self::getGameStateValue('firstPlayer');
        $secondPlayerId = $nextPlayer[$firstPlayerId];
        $thirdPlayerId = $nextPlayer[$secondPlayerId];
        $fourthPlayerId = $nextPlayer[$thirdPlayerId];

        // This is just to remember how teams are made up
        $playerToTeam = array();
        $playerToTeam[$firstPlayerId] = 1;
        $playerToTeam[$secondPlayerId] = 2;
        $playerToTeam[$thirdPlayerId] = 1;
        $playerToTeam[$fourthPlayerId] = 2;

        $firstTeamCardsWon = array_merge(
            $this->cards->getCardsInLocation('cardswon', $firstPlayerId),
            $this->cards->getCardsInLocation('cardswon', $thirdPlayerId)
        );
        $secondTeamCardsWon = array_merge(
            $this->cards->getCardsInLocation('cardswon', $secondPlayerId),
            $this->cards->getCardsInLocation('cardswon', $fourthPlayerId)
        );

        return array(
            // Using "_private" keyword, all data inside this array will be made private
            '_private' => array(
                // Data will be sent only to specific player IDs
                $firstPlayerId => array(
                    'trickswon' => $firstTeamCardsWon,
                    'teammate_hand' => array_merge($this->cards->getCardsInLocation('hand', $thirdPlayerId), array())
                ),
                $secondPlayerId => array(
                    'trickswon' => $secondTeamCardsWon,
                    'teammate_hand' => array_merge($this->cards->getCardsInLocation('hand', $fourthPlayerId), array())
                ),
                $thirdPlayerId => array(
                    'trickswon' => $firstTeamCardsWon,
                    'teammate_hand' => array_merge($this->cards->getCardsInLocation('hand', $firstPlayerId), array())
                ),
                $fourthPlayerId => array(
                    'trickswon' => $secondTeamCardsWon,
                    'teammate_hand' => array_merge($this->cards->getCardsInLocation('hand', $secondPlayerId), array())
                )
            )
        );
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stNewHand() {
        $players = self::loadPlayersBasicInfos();

        // Stats and game states variables
        self::incStat(1, "handNbr");
        self::setGameStateValue('handNumber', self::getGameStateValue('handNumber') + 1);
        self::setGameStateValue('showCardsPhaseDone', 0);

        // Current player is the dealer
        $oldDealer = self::getGameStateValue('dealer');
        $currentDealer = null;
        if ($oldDealer == -1) {
            $currentDealer = self::getActivePlayerId();
            // Next player is the first one to play
            $this->activeNextPlayer();
        } else {
            $nextPlayer = self::createNextPlayerTable(array_keys($players));
            $currentDealer = $nextPlayer[$oldDealer];
            // Player after dealer is the one who plays
            $this->gamestate->changeActivePlayer($nextPlayer[$currentDealer]);
        }

        self::setGameStateValue('dealer', $currentDealer);
        self::notifyAllPlayers('dealCards', clienttranslate('${player_name} is the dealer of this hand') , array(
            'player_id' => $currentDealer,
            'player_name' => $players[$currentDealer]['player_name']
        ));

        // Take back all cards (from any location => null) to deck
        $this->cards->moveAllCardsInLocation(null, "deck");
        $this->cards->shuffle('deck');

        $totalCards = $this->cards->countCardsInLocation('deck');

        // Choose briscola
        // N.B: The briscola index goes from 1 to len(array) because cards are indexed using auto increment IDs
        $briscolaIndex = rand(1, $totalCards);
        $briscola = $this->cards->getCardsInLocation('deck')[$briscolaIndex];
        $briscolaId = $briscola['id'];

        self::setGameStateValue('briscolaColor', $briscola['type'] );
        self::setGameStateValue('briscolaValue', $briscola['type_arg'] );
        self::setGameStateValue('briscolaCardId', $briscolaId );

        // Set big negative location_arg to briscola, so that it's drawn at last for sure
        $briscolaLocationArg = BRISCOLA_LOCATION_ARG;
        $sql = "UPDATE card SET card_location_arg='$briscolaLocationArg' WHERE card_id='$briscolaId'";
        self::DbQuery($sql);

        // Deal 3 cards to each players and give some other info to the client
        // N.B: Subtract: 3 cards per player and the briscola
        $cardsindeck = $totalCards - (count($players) * 3) - 1;

        foreach ($players as $playerId => $player) {
            $cards = $this->cards->pickCards(3, 'deck', $playerId);

            // Notify player about his cards, as well as other infos that are useful
            // when passing from one hand to another, without refreshing
            // (thus, without calling getAllData function)
            self::notifyPlayer($playerId, 'newHand', '', array (
                'cards' => $cards,
                'cardsindeck' => $cardsindeck,
                'briscola' => $briscola));
        }

        $this->gamestate->nextState("");
    }

    function stNewTrick() {
        // Reset trick color to 0 (= no color)
        self::setGameStateInitialValue('firstPlayedColor', 0);

        // If there are 4 players, and there are 0 cards in the deck,
        // we need to show each other' cards to companions as well as all taken cards during the game!
        $players = self::loadPlayersBasicInfos();
        $cardsInDeck = $this->cards->countCardInLocation('deck');
        $showCardsPhaseDone = self::getGameStateValue('showCardsPhaseDone');
        if ($cardsInDeck == 0 && count($players) == 4 && $showCardsPhaseDone == 0) {
            self::setGameStateValue('showCardsPhaseDone', 1);
            $this->gamestate->nextState("finalPhase");
        } else {
            $this->gamestate->nextState("nextPlayer");
        }
    }

    function stDrawCards() {
        // Draw cards from the deck and give them to each player.
        // Instead of implementing a real turn, we make each player draw the first card
        // on the deck, exception made for the briscola card.
        $players = self::loadPlayersBasicInfos();
        $numberOfPlayers = count($players);
        $lastWinnerId = self::getActivePlayerId();

        $cardsInDeckCount = $this->cards->countCardInLocation('deck');
        $lastWinnerPosition = $players[$lastWinnerId]['player_no'];
        for ($i = 0; $i < $numberOfPlayers; $i++) {
            $playerIdGiveCardTo = null;
            foreach ($players as $player_id => $player) {
                $currentPlayerPosition = $players[$player_id]['player_no'];

                $nextPlayerPosition = $lastWinnerPosition + $i;
                if ($nextPlayerPosition > $numberOfPlayers) {
                    $nextPlayerPosition = $nextPlayerPosition % $numberOfPlayers;
                }

                if ($currentPlayerPosition == $nextPlayerPosition) {
                    $playerIdGiveCardTo = $player_id;
                    break;
                }
            }

            // This is a convoluted way to represent deck draws:
            // we create as many DOM nodes representing the deck as there are cards in the actual deck,
            // and proceed to delete 2/4 of them after every draw.
            $card = $this->cards->pickCard('deck', $playerIdGiveCardTo);
            $currentRemainingCards = $cardsInDeckCount - $i - 1;
            $remainingCardsAfterTurn = max($cardsInDeckCount - $numberOfPlayers - 1, 0);
            if ($currentRemainingCards > 0) {
                self::notifyPlayer($playerIdGiveCardTo, 'drawNewCard', '', array (
                    'card' => $card,
                    'card_index_to_pick'=> $cardsInDeckCount - 1,
                    'remaining_cards' => $remainingCardsAfterTurn,
                    'id_briscola' => self::getGameStateValue('briscolaCardId')));
            } else {
                self::notifyPlayer($playerIdGiveCardTo, 'drawNewCard', '', array (
                    'card' => $card,
                    'card_index_to_pick'=> 0,
                    'remaining_cards' => 0,
                    'id_briscola' => self::getGameStateValue('briscolaCardId')));
            }

        }

        $this->gamestate->nextState("");
    }

    function stNextPlayer() {
        $players = self::loadPlayersBasicInfos();
        $numberOfPlayers = count($players);

        // Active next player OR end the trick and go to the next trick OR end the hand
        if ($this->cards->countCardInLocation('cardsontable') == $numberOfPlayers) {
            // This is the end of the trick
            $cardsOnTable = $this->cards->getCardsInLocation('cardsontable');
            $bestValue = 0;
            $bestValuePlayerId = null;
            $currentTrickColor = self::getGameStateValue('firstPlayedColor');
            $semeBriscola = self::getGameStateValue('briscolaColor');

            $semeCorrente = $currentTrickColor;
            $primaBriscolaTrovata = false;
            foreach ( $cardsOnTable as $card ) {
                if ($card ['type'] == $semeBriscola) {
                    if (!$primaBriscolaTrovata) {
                        $semeCorrente = $semeBriscola;
                        // Note: location_arg = player who played this card on table
                        $bestValuePlayerId = $card ['location_arg'];
                        // Note: type_arg = value of the card
                        $bestValue = $card ['type_arg'];
                        $primaBriscolaTrovata = true;
                        continue;
                    }
                }
                if ($card ['type'] == $semeCorrente) {
                    if ($bestValuePlayerId === null || $card ['type_arg'] > $bestValue) {
                        // Note: location_arg = player who played this card on table
                        $bestValuePlayerId = $card ['location_arg'];
                        // Note: type_arg = value of the card
                        $bestValue = $card ['type_arg'];
                    }
                }
            }

            // Active this player => he's the one who starts the next trick
            $this->gamestate->changeActivePlayer($bestValuePlayerId);

            // Move all cards to "cardswon" of the given player
            $this->cards->moveAllCardsInLocation('cardsontable', 'cardswon', null, $bestValuePlayerId);

            // Notify
            // Note: we use 2 notifications here in order we can pause the display during the first notification
            // before we move all cards to the winner (during the second)
            self::notifyAllPlayers( 'trickWin', clienttranslate('${player_name} wins the trick'), array(
                'player_id' => $bestValuePlayerId,
                'player_name' => $players[$bestValuePlayerId]['player_name']
            ));

            self::notifyAllPlayers( 'giveAllCardsToPlayer','', array(
                'player_id' => $bestValuePlayerId
            ));

            // Decide what state is next
            if ($this->cards->countCardInLocation('hand') == 0) {
                // End of the hand
                $this->gamestate->nextState("endHand");
            } else {
                // End of the trick
                if ($this->cards->countCardInLocation('deck') > 0) {
                    $this->gamestate->nextState("nextCards");
                } else {
                    $this->gamestate->nextState("nextTrick");
                }
            }
        } else {
            // Standard case (not the end of the trick)
            // => just active the next player
            $playerId = self::activeNextPlayer();
            self::giveExtraTime($playerId);
            $this->gamestate->nextState('nextPlayer');
        }
    }

    function stShowCards() {
        // Active all players: everyone can have a peek at old cards/teammate cards
        $this->gamestate->setAllPlayersMultiactive();
    }

    function stEndHand() {
        // Count and score points, then end the game or go to the next hand.
        $players = self::loadPlayersBasicInfos();

        $playersToPoints = array();
        $playersToBriscolaCards = array();
        foreach ($players as $playerId => $player) {
            $playersToPoints[$playerId] = 0;
            $playersToBriscolaCards[$playerId] = 0;
        }

        $briscolaColor = self::getGameStateValue('briscolaColor');
        
        $cards = $this->cards->getCardsInLocation("cardswon");
        foreach ($cards as $card) {
            $playerId = $card['location_arg'];

            /**
             * Summary of briscola's cards and their values
             *
             * Cards nominal values:    2  4  5  6  7  J  Q  K  3  A
             * Cards internal values:   2  3  4  5  6  7  8  9  10 11
             * Cards points:            0  0  0  0  0  2  3  4  10 11
             */
            if ($card['type_arg'] == 7) {
            	/* J */
            	$playersToPoints[$playerId] += 2;
            } else if ($card['type_arg'] == 8) {
            	/* Q */
            	$playersToPoints[$playerId] += 3;
            } else if ($card['type_arg'] == 9) {
            	/* K */
            	$playersToPoints[$playerId] += 4;
            } else if ($card['type_arg'] == 10) {
            	/* Tre */
            	$playersToPoints[$playerId] += 10;
            } else if ($card['type_arg'] == 11) {
            	/* Asso */
        		$playersToPoints[$playerId] += 11;
            }

            // Compute briscola stats
            if ($card['type'] == $briscolaColor) {
                $playersToBriscolaCards[$playerId] += 1;
            }
        }

        // Add team logic when dealing with 4 players
        if (count($players) == 4) {
            $nextPlayer = self::createNextPlayerTable(array_keys($players));
            $firstPlayerId = self::getGameStateValue('firstPlayer');
            $secondPlayerId = $nextPlayer[$firstPlayerId];
            $thirdPlayerId = $nextPlayer[$secondPlayerId];
            $fourthPlayerId = $nextPlayer[$thirdPlayerId];

            $playerToTeam = array();
            $playerToTeam[$firstPlayerId] = 1;
            $playerToTeam[$secondPlayerId] = 2;
            $playerToTeam[$thirdPlayerId] = 1;
            $playerToTeam[$fourthPlayerId] = 2;

            $teamToPoints = array(
                1 => 0,
                2 => 0
            );
            foreach ($playersToPoints as $playerId => $points) {
                $teamToPoints[$playerToTeam[$playerId]] += $points;
            }

            $teamToBriscolaCards = array(
                1 => 0,
                2 => 0
            );
            foreach ($playersToBriscolaCards as $playerId => $briscolaCards) {
                $teamToBriscolaCards[$playerToTeam[$playerId]] += $briscolaCards;
            }

            $teamToPlayers = array(
                1 => array(
                    1 => $firstPlayerId,
                    2 => $thirdPlayerId
                ),
                2 => array(
                    1 => $secondPlayerId,
                    2 => $fourthPlayerId
                )
            );
        }

        // Notify score to players
        // N.B: All the parts of code regarding notifications/stats have been duplicated
        // in order to take into account the differences between 2/4 players games
        if (count($players) == 2) {
            foreach ($playersToPoints as $playerId => $points) {
                self::notifyAllPlayers("points", clienttranslate('${player_name} makes ${points} points'), array (
                    'player_id' => $playerId,
                    'player_name' => $players[$playerId]['player_name'],
                    'points' => $points));

                if ($points >= BIG_SCORE) {
                    self::incStat(1, "bigScore", $playerId);
                }

                if ($points == PERFECT_SCORE) {
                    self::incStat(1, "perfectScore", $playerId);
                }
            }
        } else if (count($players) == 4) {
            foreach ($teamToPoints as $teamId => $points) {
                $p1Id = $teamToPlayers[$teamId][1];
                $p2Id = $teamToPlayers[$teamId][2];

                self::notifyAllPlayers("points", clienttranslate('Team ${player_name_1} and ${player_name_2} make ${points} points'), array (
                    'player_name_1' => $players[$p1Id]['player_name'],
                    'player_name_2' => $players[$p2Id]['player_name'],
                    'points' => $points));

                if ($points >= BIG_SCORE) {
                    self::incStat(1, "bigScore", $p1Id);
                    self::incStat(1, "bigScore", $p2Id);
                }

                if ($points == PERFECT_SCORE) {
                    self::incStat(1, "perfectScore", $p1Id);
                    self::incStat(1, "perfectScore", $p2Id);
                }
            }
        }

        // Apply score to players and notify hand winner or draw
        if (count($players) == 2) {
            foreach ($playersToPoints as $playerId => $points) {
                if ($points >= MINIMUM_WINNING_SCORE) {
                    $sql = "UPDATE player SET player_score=player_score+1 WHERE player_id='$playerId'";
                    self::DbQuery($sql);

                    self::notifyAllPlayers("points", clienttranslate('${player_name} wins the hand'), array (
                        'player_id' => $playerId,
                        'player_name' => $players [$playerId] ['player_name']));
                } else if ($points == DRAW_SCORE) {
                    self::notifyAllPlayers("points", clienttranslate('Draw!'));
                    break;
                }
            }
        } else if (count($players) == 4) {
            foreach ($teamToPoints as $teamId => $points) {
                $p1Id = $teamToPlayers[$teamId][1];
                $p2Id = $teamToPlayers[$teamId][2];

                if ($points >= MINIMUM_WINNING_SCORE) {
                    $sql = "UPDATE player SET player_score=player_score+1 WHERE player_id='$p1Id'";
                    self::DbQuery($sql);

                    $sql = "UPDATE player SET player_score=player_score+1 WHERE player_id='$p2Id'";
                    self::DbQuery($sql);

                    self::notifyAllPlayers("points", clienttranslate('Team ${player_name_1} and ${player_name_2} wins the hand'), array (
                        'player_name_1' => $players[$p1Id]['player_name'],
                        'player_name_2' => $players[$p2Id]['player_name']));
                } else if ($points == DRAW_SCORE) {
                    self::notifyAllPlayers("points", clienttranslate('Draw!'));
                    break;
                }
            }
        }


        // Check briscola stats
        if (count($players) == 2) {
            foreach ($playersToBriscolaCards as $playerId => $briscolaNumber) {
                if ($briscolaNumber == 0) {
                    self::incStat(1, "noBriscola", $playerId);
                } else if ($briscolaNumber == ALL_BRISCOLA_CARDS) {
                    self::incStat(1, "allBriscola", $playerId);
                }
            }
        } else if (count($players) == 4) {
            foreach ($teamToBriscolaCards as $teamId => $briscolaNumber) {
                $p1Id = $teamToPlayers[$teamId][1];
                $p2Id = $teamToPlayers[$teamId][2];

                if ($briscolaNumber == 0) {
                    self::incStat(1, "noBriscola", $p1Id);
                    self::incStat(1, "noBriscola", $p2Id);
                } else if ($briscolaNumber == ALL_BRISCOLA_CARDS) {
                    self::incStat(1, "allBriscola", $p1Id);
                    self::incStat(1, "allBriscola", $p2Id);
                }
            }
        }

        $newScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true);
        self::notifyAllPlayers("newScores", '', array('newScores' => $newScores));

        if (count($players) == 4) {
            $teamToNewScores = array(
                1 => 0,
                2 => 0
            );
            foreach ($newScores as $playerId => $score) {
                // We override these variables twice, but we don't care since the score
                // is the same for each member of the team
                $teamToNewScores[$playerToTeam[$playerId]]  = $score;
            }
        }

        // Display table window with results
        $table = array();

        // Header line
        $firstRow = array('');
        if (count($players) == 2) {
            foreach($players as $playerId => $player) {
                $firstRow[] = array(
                    'str' => '${player_name}',
                    'args' => array('player_name' => $player['player_name']),
                    'type' => 'header'
                );
            }
        } else if (count($players) == 4) {
            foreach($teamToPlayers as $teamId => $teamPlayers) {
                $p1Id = $teamPlayers[1];
                $p2Id = $teamPlayers[2];
                $teamName = $players[$p1Id]['player_name'] . " and " . $players[$p2Id]['player_name'];

                $firstRow[] = array(
                    'str' => '${team_name}',
                    'args' => array('team_name' => $teamName),
                    'type' => 'header'
                );
            }
        }

        $table[] = $firstRow;

        // Points
        $newRow = array(array('str' => clienttranslate('Current game points'), 'args' => array()));
        if (count($players) == 2) {
            foreach($playersToPoints as $playerId => $points) {
                if ($points >= MINIMUM_WINNING_SCORE) {
                    $newRow[] = clienttranslate("" . $points . " (<b>Win</b>)");
                } else if ($points == DRAW_SCORE) {
                    $newRow[] = clienttranslate("" . $points . " (<b>Draw</b>)");
                } else {
                    $newRow[] = clienttranslate("" . $points . " (<b>Lose</b>)");
                }
            }
        } else if (count($players) == 4) {
            foreach($teamToPoints as $teamId => $points) {
                if ($points >= MINIMUM_WINNING_SCORE) {
                    $newRow[] = clienttranslate("" . $points . " (<b>Win</b>)");
                } else if ($points == DRAW_SCORE) {
                    $newRow[] = clienttranslate("" . $points . " (<b>Draw</b>)");
                } else {
                    $newRow[] = clienttranslate("" . $points . " (<b>Lose</b>)");
                }
            }
        }

        $table[] = $newRow;

        // Final score
        $newRow = array(array('str' => clienttranslate('Overall score'), 'args' => array()));
        if (count($players) == 2) {
            foreach($newScores as $playerId => $score) {
                $newRow[] = "<b>" . $score . "</b>";
            }
        } else if (count($players) == 4) {
            foreach($teamToNewScores as $teamId => $score) {
                $newRow[] = "<b>" . $score . "</b>";
            }
        }
        $table[] = $newRow;

        $this->notifyAllPlayers("tableWindow", '', array(
            "id" => 'finalScoring',
            "title" => clienttranslate("Result of this hand"),
            "table" => $table,
            "closing" => "Close"
        ));

        // End game
        $winningHandsToEndGame = self::getGameStateValue('winningHandsToEndGame');
        foreach($newScores as $playerId => $score) {
            if($score >= $winningHandsToEndGame) {
                // Trigger the end of the game !
                $this->gamestate->nextState("endGame");
                return;
            }
        }

        // Otherwise... new hand !
        $this->gamestate->nextState("nextHand");
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
    */

    function zombieTurn($state, $activePlayer)
    {
        if ($state['type'] === "activeplayer") {
            if ($state['name'] == "playerTurn") {
                $playerCards = $this->cards->getCardsInLocation("hand", $activePlayer);
                // Play the first card
                foreach($playerCards as $card_id => $card) {
                    self::playCard($card_id, true);
                    break;
                }
            }

            return;
        }

        throw new feException("Zombie mode not supported at this game state: " . $state['name']);
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb( $from_version )
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            $sql = "ALTER TABLE xxxxxxx ....";
//            self::DbQuery( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            $sql = "CREATE TABLE xxxxxxx ....";
//            self::DbQuery( $sql );
//        }
//        // Please add your future database scheme changes here
//
//


    }    
}
