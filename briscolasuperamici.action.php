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
 * briscolasuperamici.action.php
 *
 * BriscolaSuperamici main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/briscolasuperamici/briscolasuperamici/myAction.html", ...)
 *
 */
  
  
  class action_briscolasuperamici extends APP_GameAction
  { 
    // Constructor: please do not modify
    public function __default()
    {
        if( self::isArg( 'notifwindow') )
        {
            $this->view = "common_notifwindow";
            $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
        }
        else
        {
            $this->view = "briscolasuperamici_briscolasuperamici";
            self::trace( "Complete reinitialization of board game" );
      }
    }

    public function playCard() {
        self::setAjaxMode();
        $card_id = self::getArg("id", AT_posint, true);
        $this->game->playCard($card_id, false);
        self::ajaxResponse();
    }

    public function endShowCards() {
        self::setAjaxMode();
        $this->game->endShowCards();
        self::ajaxResponse();
    }
  }
  

