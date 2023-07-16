<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * BagOfChips implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * bagofchips.action.php
 *
 * BagOfChips main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/bagofchips/bagofchips/myAction.html", ...)
 *
 */
  
  
  class action_bagofchips extends APP_GameAction
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
            $this->view = "bagofchips_bagofchips";
            self::trace( "Complete reinitialization of board game" );
      }
  	} 

    public function discardCards() {
        self::setAjaxMode();   

        $idsStr = self::getArg( "ids", AT_numberlist, true );
        $ids = array_map(fn($str) => intval($str), explode(',', $idsStr));
        $this->game->discardCards($ids);

        self::ajaxResponse();
    }

    public function placeCards() {
        self::setAjaxMode();   

        $minusStr = self::getArg( "minus", AT_numberlist, true );
        $minus = array_map(fn($str) => intval($str), explode(',', $minusStr));
        $plusStr = self::getArg( "plus", AT_numberlist, true );
        $plus = array_map(fn($str) => intval($str), explode(',', $plusStr));
        $this->game->placeCards($minus, $plus);

        self::ajaxResponse();
    }
  }
  

