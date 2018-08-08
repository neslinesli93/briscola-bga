<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * BriscolaSuperamici implementation : © <Your name here> <Your email address here>
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
            "handNumber" => 14
        ));

        // Init $this->cards to be a deck
        $this->cards = self::getNew( "module.common.deck" );
        $this->cards->init( "card" );

        // Assign a big negative value to briscola card, so that it's drawn as last card
        $this->briscolaLocationArg = -1000;

        // Various points related stuff
        $this->drawScore = 60;
        $this->minimumWinningScore = 61;
        $this->winningHandsToEndGame = 3;
        $this->numberOfCardsInFullDeck = 40;

        // Stats related variables
        $this->bigScore = 100;
        $this->perfectScore = 120;
        $this->allBriscolaCards = 10;
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
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];
 
        // Create players
        // Note: if you added some extra field on "player" table in the database (dbmodel.sql), you can initialize it there.
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = array();
        foreach( $players as $player_id => $player )
        {
            $color = array_shift( $default_colors );
            $values[] = "('".$player_id."','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
        }
        $sql .= implode( $values, ',' );
        self::DbQuery( $sql );
        self::reattributeColorsBasedOnPreferences( $players, $gameinfos['player_colors'] );
        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        self::setGameStateInitialValue('firstPlayedColor', 0);
        self::setGameStateInitialValue('briscolaColor', 0);
        self::setGameStateInitialValue('briscolaValue', 0);
        self::setGameStateInitialValue('briscolaCardId', 0);
        self::setGameStateInitialValue('handNumber', 0);

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
            for ($value = 2; $value <= 11; $value ++) {
                //  2, 4, 5 ... K, 3, A
                $cards [] = array ('type' => $colorId,'type_arg' => $value,'nbr' => 1);
            }
        }

        // Create cards deck
        $this->cards->createCards( $cards, 'deck');

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

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
        $result['players'] = self::getCollectionFromDb( $sql );

        // Gather all information about current game situation (visible by player $current_player_id).

        // Cards in player hand
        $result['hand'] = $this->cards->getCardsInLocation( 'hand', $currentPlayerId );

        // Cards played on the table
        $result['cardsontable'] = $this->cards->getCardsInLocation( 'cardsontable' );

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
        $currentCardsInDeck = $this->cards->countCardsInLocation('deck');
        $drawnCardRatio = ($this->numberOfCardsInFullDeck - $currentCardsInDeck) / (float) $this->numberOfCardsInFullDeck;

        $userScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true );
        $maxScore = 0;
        foreach( $userScores as $playerId => $score ) {
            if ($score > $maxScore) {
                $maxScore = $score;
            }
        }

        $base = 1 / (float) $this->winningHandsToEndGame * ($maxScore) * 100;
        $current = $drawnCardRatio * 100 / (float) $this->winningHandsToEndGame;
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
            $directions = array('S', 'W', 'N', 'E');
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

        while(count( $directions ) > 0) {
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
            'color' => $currentCard ['type'],
            'color_displayed' => $this->colors[$currentCard['type']]['name']
            ));

        $this->gamestate->nextState('playCard');
    }
    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    function argGiveCards() {
        return array ();
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stNewHand() {
        // Stats and game states variables
        self::incStat(1, "handNbr");
        self::setGameStateValue('handNumber', self::getGameStateValue('handNumber') + 1);

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
        $sql = "UPDATE card SET card_location_arg=$this->briscolaLocationArg WHERE card_id='$briscolaId'";
        self::DbQuery($sql);

        // Deal 3 cards to each players and give some other info to the client
        $players = self::loadPlayersBasicInfos();

        // Subtract: 3 cards per player and the briscola
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
        // Active the player who wins the last trick
        // Reset trick color to 0 (= no color)
        self::setGameStateInitialValue('firstPlayedColor', 0);
        $this->gamestate->nextState("");
    }

    function stDrawCards() {
        // Pesca le carte dal mazzo e le da' a ciascun giocatore.
        // Anziche' implementare un giro effettivo di pescate (come la briscola offline),
        // facciamo pescare a tutti la prima carta nel deck, tranne quando l'ultimo deve pescare la briscola
        $players = self::loadPlayersBasicInfos();
        $numberOfPlayers = count($players);
        $lastWinnerId = self::getActivePlayerId();

        $cardsInDeckCount = $this->cards->countCardInLocation('deck');
        $lastWinnerPosition = $players[$lastWinnerId]['player_no'];
        for ($i = 0; $i < $numberOfPlayers; $i++) {
            $playerIdGiveCardTo = null;
            foreach ( $players as $player_id => $player ) {
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

            $card = $this->cards->pickCard('deck', $playerIdGiveCardTo);
            $remainingCards = $cardsInDeckCount - $i - 1;
            $remainingCardsDeckLabel = max($cardsInDeckCount - $numberOfPlayers - 1, 0);
            if ($remainingCards > 0) {
                self::notifyPlayer($playerIdGiveCardTo, 'drawNewCard', '', array (
                    'card' => $card,
                    'deck_index_to_pick'=> $cardsInDeckCount - 1,
                    'deck_index_to_start_delete_from' => $cardsInDeckCount - 1,
                    'decks_to_delete' => $numberOfPlayers,
                    'remaining_cards_deck_label' => $remainingCardsDeckLabel,
                    'delete_briscola_from_deck' => $remainingCardsDeckLabel == 0,
                    'id_briscola' => self::getGameStateValue('briscolaCardId')));
            } else {
                self::notifyPlayer($playerIdGiveCardTo, 'drawNewCard', '', array (
                    'card' => $card,
                    'deck_index_to_pick'=> 0,
                    'deck_index_to_start_delete_from' => $cardsInDeckCount - 1,
                    'decks_to_delete' => $numberOfPlayers,
                    'remaining_cards_deck_label' => 0,
                    'delete_briscola_from_deck'=> false,
                    'id_briscola' => self::getGameStateValue('briscolaCardId')));
            }

        }

        $this->gamestate->nextState("");
    }

    function stNextPlayer() {
        // Active next player OR end the trick and go to the next trick OR end the hand
        // TODO: Vedere quanti giocatori stanno giocando e selezionare il numero giusto
        if ($this->cards->countCardInLocation('cardsontable') == 2) {
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
            $players = self::loadPlayersBasicInfos();
            self::notifyAllPlayers( 'trickWin', clienttranslate('${player_name} wins the trick'), array(
                'player_id' => $bestValuePlayerId,
                'player_name' => $players[$bestValuePlayerId]['player_name']
            ));

            self::notifyAllPlayers( 'giveAllCardsToPlayer','', array(
                'player_id' => $bestValuePlayerId
            ));


            // TODO: If there are 4 players, and there are 0 cards in the deck,
            // we need to show each other' cards to companions.

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

    function stEndHand() {
        // Count and score points, then end the game or go to the next hand.
        $players = self::loadPlayersBasicInfos();

        $playersToPoints = array();
        $playersToBriscolaCards = array();
        foreach ( $players as $playerId => $player ) {
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

        // TODO: Add team logic when dealing with 4 players
        
        // Notify score to players
        foreach ($playersToPoints as $playerId => $points) {
            self::notifyAllPlayers("points", clienttranslate('${player_name} makes ${points} points'), array (
                'player_id' => $playerId,
                'player_name' => $players[$playerId]['player_name'],
                'points' => $points));

            if ($points >= $this->bigScore) {
                self::incStat(1, "bigScore", $playerId);
            }

            if ($points == $this->perfectScore) {
                self::incStat(1, "perfectScore", $playerId);
            }
        }

        // Apply score to players and notify hand winner or draw
        foreach ($playersToPoints as $playerId => $points) {
            if ($points >= $this->minimumWinningScore) {
                $sql = "UPDATE player SET player_score=player_score+1  WHERE player_id='$playerId'";
                self::DbQuery($sql);

                self::notifyAllPlayers("points", clienttranslate('${player_name} wins the hand'), array (
                    'player_id' => $playerId,
                    'player_name' => $players [$playerId] ['player_name']));
            } else if ($points == $this->drawScore) {
                self::notifyAllPlayers("points", clienttranslate('Draw!'));
            }
        }

        // Check briscola stats
        foreach ($playersToBriscolaCards as $playerId => $briscolaNumber) {
            if ($briscolaNumber == 0) {
                self::incStat(1, "noBriscola", $playerId);
            } else if ($briscolaNumber == $this->allBriscolaCards) {
                self::incStat(1, "allBriscola", $playerId);
            }
        }

        $newScores = self::getCollectionFromDb("SELECT player_id, player_score FROM player", true);
        self::notifyAllPlayers("newScores", '', array( 'newScores' => $newScores ));

        // Display table window with results

        $table = array();

        // Header line
        $firstRow = array('');
        foreach($players as $playerId => $player) {
            $firstRow[] = array(
                'str' => '${player_name}',
                'args' => array( 'player_name' => $player['player_name'] ),
                'type' => 'header'
            );
        }
        $table[] = $firstRow;

        // Points
        $newRow = array(array('str' => clienttranslate('Current game points'), 'args' => array()));
        foreach($playersToPoints as $playerId => $points) {
            if ($points >= $this->minimumWinningScore) {
                $newRow[] = clienttranslate("" . $points . " (<b>Win</b>)");
            } else if ($points == $this->drawScore) {
                $newRow[] = clienttranslate("" . $points . " (<b>Draw</b>)");
            } else {
                $newRow[] = clienttranslate("" . $points . " (<b>Lose</b>)");
            }
        }
        $table[] = $newRow;

        // Final score
        $newRow = array(array('str' => clienttranslate('Overall score'), 'args' => array()));
        foreach($newScores as $playerId => $score) {
            $newRow[] = "<b>" . $score . "</b>";
        }
        $table[] = $newRow;

        $this->notifyAllPlayers("tableWindow", '', array(
            "id" => 'finalScoring',
            "title" => clienttranslate("Result of this hand"),
            "table" => $table,
            "closing" => "Close"
        ));

        // End game
        foreach($newScores as $playerId => $score) {
            if($score >= $this->winningHandsToEndGame) {
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
                    $this->game->playCard($card_id, true);
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
