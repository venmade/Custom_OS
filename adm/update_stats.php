<?php

  $time = microtime();
  $time = explode(' ', $time);
  $time = $time[1] + $time[0];
  $start = $time;

include("../config.php");

	include('../lang/'.$default_language.'.php');
	include("../inc/common.php");
if ( OS_is_logged() AND OS_is_admin() ) {
	//include("../inc/class.database.php");
	require_once('../inc/class.db.PDO.php'); 
	include("../inc/db_connect.php");
	
if ( file_exists('../'.OS_CURRENT_THEME_PATH.'/functions.php') )
    include('../'.OS_CURRENT_THEME_PATH.'/functions.php');

$return = "";

function OS_UpdateScoresTable( $name = "" ) {
    global $db;
	
	$name = OS_StrToUTF8( trim($name) );
	if ( !empty($name) ) {
	$sth = $db->prepare("SELECT * FROM scores WHERE (name) = ('".$name."')");
	$result = $sth->execute();
    if( $limit = $sth->rowCount() <= 0 ) {
    $sth = $db->prepare("INSERT INTO scores(category, name)VALUES('dota_elo','".$name."')");
	$result = $sth->execute();
    }
	
    //Get updated result
    $resultScore = $db->prepare("SELECT player,score FROM ".OSDB_STATS." WHERE (player) = ('".$name."')");
	$result = $resultScore->execute();
    $rScore = $resultScore->fetch(PDO::FETCH_ASSOC);
    //update "scores" table
    $UpdateScoreTable = $db->prepare("UPDATE `scores` SET `score` = '".$rScore["score"]."' 
	WHERE (name) = ('".$rScore["player"]."') ");
	$result = $UpdateScoreTable->execute();
	}
}


	if (isset($_GET["reset"])) {
	$r1 = $db->prepare("UPDATE ".OSDB_GAMES." SET stats = 0 WHERE stats = 1");
	$result = $r1->execute(); 
	//$r2 = $db->query("DELETE FROM ".OSDB_STATS."");
	//$r3 = $db->query("ALTER table ".OSDB_STATS." auto_increment = 1");
	$r4 = $db->prepare("TRUNCATE table ".OSDB_STATS." ");
	$result = $r4->execute(); 
	}
	
	if ( isset($_GET["start"]) ) {
	//GET ALL ADMINS (in array)
	$sth = $db->prepare("SELECT * FROM ".OSDB_ADMINS." WHERE id>=1");
	$result = $sth->execute(); 
	$admins = array();
	while ($row = $sth->fetch(PDO::FETCH_ASSOC)) { $admins[]= strtolower($row["name"]);  }
	//GET ALL USERS FROM SAFELIST
	$sth = $db->prepare("SELECT * FROM ".OSDB_SAFELIST." WHERE id>=1");
	$result = $sth->execute(); 
	$safelist = array();
	while ($row = $sth->fetch(PDO::FETCH_ASSOC)) { $safelist[]= strtolower($row["name"]);	}
	
	$sth = $db->prepare( "SELECT id FROM ".OSDB_GAMES." 
	WHERE (map) LIKE ('%".$MapString."%') AND stats = 0 AND duration>='".$MinDuration."' ORDER BY `id` LIMIT ".$updateGames." " );
	$result = $sth->execute(); 
	
	 while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {

	 $gid = $row["id"];
        	$minimumplayercheck = $db->prepare( "SELECT COUNT(*) FROM
	        ".OSDB_GP." AS gp
       		LEFT JOIN ".OSDB_GAMES." AS g ON gp.gameid=g.id
       		WHERE g.duration-gp.left < '".$LeftTimePenalty."' AND g.id='".$row["id"]."';" );
        	$result = $minimumplayercheck->execute();
		while ($list = $minimumplayercheck->fetch(PDO::FETCH_ASSOC)) {
			$recentstayedplayers=$list["COUNT(*)"];
		}
                $minimumplayercheck2 = $db->prepare( "SELECT COUNT(gp.id) FROM
                ".OSDB_GP." AS gp
                LEFT JOIN ".OSDB_GAMES." AS g ON gp.gameid=g.id
                WHERE g.id='".$row["id"]."';" );
                $result = $minimumplayercheck2->execute();
                while ($list = $minimumplayercheck2->fetch(PDO::FETCH_ASSOC)) {
                        $recentplayers=$list["COUNT(gp.id)"];
                }

  if( $recentstayedplayers < $MinEndedPlayers OR $recentplayers < $MinStartedPlayers )
  {
   $update = $db->prepare("UPDATE ".OSDB_GAMES." SET stats = 1 WHERE id = '".$gid."' ");
   $result = $update->execute();
   $return.="\nSkipped Game ($gid), to less players(".$recentplayers."/".$recentstayedplayers.")";
  }

	 $sth2 = $db->prepare("SELECT winner, dp.gameid, gp.colour, newcolour, kills, deaths, assists, creepkills, creepdenies, neutralkills, towerkills, gold,  raxkills, courierkills, g.duration as duration, g.gamename,
	   gp.name as name, 
	   gp.ip as ip, gp.spoofed, gp.loadingtime, gp.spoofedrealm, gp.reserved, gp.left, gp.leftreason,
	   b.name as banname, b.expiredate, b.warn
	   FROM ".OSDB_DP." AS dp 
	   LEFT JOIN ".OSDB_GP." AS gp ON gp.gameid = dp.gameid and dp.colour = gp.colour 
	   LEFT JOIN ".OSDB_DG." AS dg ON dg.gameid = dp.gameid 
	   LEFT JOIN ".OSDB_GAMES." AS g ON g.id = dp.gameid 
	   LEFT JOIN ".OSDB_BANS." as b ON b.name=LOWER(gp.name)
	   WHERE dp.gameid='".$gid."'
	   GROUP by gp.name
	   ORDER BY newcolour");
	   $result = $sth2->execute(); 
	   
           $temp_points  = 0;
	   
	   if ($sth2->rowCount() <=0)  { 
	   $update = $db->prepare("UPDATE ".OSDB_GAMES." SET stats = 1 WHERE id = '".$gid."' ");
	   $result = $update->execute(); 
	   }
	   while ($list = $sth2->fetch(PDO::FETCH_ASSOC)) {
		$kills=$list["kills"];
		$deaths=$list["deaths"];
		$assists=$list["assists"];
		$creepkills=$list["creepkills"];
		$creepdenies=$list["creepdenies"];
		$neutralkills=$list["neutralkills"];
		$towerkills=$list["towerkills"];
		$raxkills=$list["raxkills"];
		$courierkills=$list["courierkills"];
		$duration=$list["duration"];
		$name=OS_StrToUTF8(trim($list["name"]));
		$IPaddress = $list["ip"];
		$banname=$list["banname"];
		$win=$list["winner"];
		$newcolour=$list["newcolour"];
		$warn_expire = $list["expiredate"];
		$warn = $list["warn"];
		$gamename = $list["gamename"];
		
		$spoofed = $list["spoofed"];
		$realm = $list["spoofedrealm"];
		//$loadingtime = $list["loadingtime"];
		$reserved = $list["reserved"];
				
		if ( empty($warn_expire ) ) $warn_expire  = '0000-00-00 00:00:00'; 
		
		if ( $warn>=1 ) $warn_qry = 'warn = '.$warn.', '; else $warn_qry = "";
				
		if ( in_array( strtolower($name), $admins ) )   $is_admin = 1; else $is_admin = 0;
		if ( in_array( strtolower($name), $safelist ) ) $is_safe = 1;  else $is_safe  = 0;
		
		if ( isset($banname) AND !empty($banname) ) $BANNED = 1; else $BANNED = 0;
		if ($win==1 AND $newcolour<=5) {$winner = 1; $loser = 0;}
		if ($win==0) {$winner = 0; $loser = 0;}
		if ($win==2 AND $newcolour>5) {$winner = 1; $loser = 0;}
		if ($win==1 AND $newcolour>5) {$winner = 0; $loser = 1;}
		if ($win==2 AND $newcolour<=5) {$winner = 0; $loser = 1;}
		
		if ($winner == 1) $score = $ScoreStart + $ScoreWins;
		if ($winner == 0) $score = $ScoreStart - $ScoreLosses;
		if ($win==0) { $score = $ScoreStart; $leaver = 0; }
		
                if ( !isset($BestPlayer)  )     $BestPlayer = ($list["name"]);

                $score_points = ($list["kills"] -  $list["deaths"]) + ($list["assists"]*0.3);
                if ( $score_points > $temp_points ) {
                $BestPlayer = ($list["name"]);
                $temp_points = $score_points;
                }

                // Score formula for each game (uncomment below to user score formula)
                // $scoreFormula = (((($kills-$deaths+$assists*0.5+$towerkills*0.5+$raxkills*0.2+($courierkills+$creepdenies)*0.1+$neutralkills*0.03+$creepkills*0.03) * .2)+($score)));

                // $score = $scoreFormula;

		if( !isset($list["leftreason"]) AND empty($list["leftreason"]) ) $list["leftreason"] = "No entry!"; 
		if ( ( $list["left"] <= ( $list["duration"] - $MinDuration ) ) AND $list["leftreason"] == "has left the game voluntarily" )  {
		   $leaver = 1; $score = "";
		} else $leaver = 0;
		
		// MEW FIELDS: $realm $reserved
		
		if ($win==0) $draw = 1; else $draw = 0;
		if (!empty($name) AND $duration >= $MinDuration) {
		$realscore = $score;
		//Leaver
		if( ( $list["left"] <= ( $list["duration"] - $LeftTimePenalty ) ) AND $list["leftreason"] == "has left the game voluntarily" AND $leaver == 0 ) {
		$score = $ScoreStart - $ScoreDisc; $winner = 0; $loser = 0;
		}
                //DISC
                $splitreason = explode( " ", $list["leftreason"] );
                if( $splitreason[1] == "lost" ) $dc = 1; else $dc = 0;

/** CUSTOM AUTOBAN **/
                //preperation
                $games = 1;
                $dc_count = 0;
                $leave_count = 0;
                //check the player
                $GetUserInfo = $db->prepare("SELECT * FROM ".OSDB_STATS." WHERE player = '".$name."'");
                $result = $GetUserInfo->execute();
                while ($list = $GetUserInfo->fetch(PDO::FETCH_ASSOC)) {
                        $games=$list["games"];
                        $dc_count=$list["dc_count"];
                        $leave_count=$list["leaver"];
			$alreadybanned=$list["banned"];
                }

                //calculations
                //Current Leave
                $games = $games+1;
                if( $leaver == 1 ) $leave_count = $leave_count+1;
                if( $dc == 1 ) $dc_count = $dc_count+1;
                $leaveratio = round( (($leave_count/$games)*100), 2 );
                $dcratio = round( (($dc_count/$games)*100), 2 );
		$lname = strtolower( $name );
                        //Check players with lower games than 5 for a high amount of leaving (over or 3 out of 5 games is to much)
                        if( $games <= 5 AND $leave_count >= 3  AND $is_admin == "0" AND $is_safe == "0" AND $alreadybanned == 0 AND $BANNED == 0 ) {
                                $reason = "AUTOBAN: Player left ".$leave_count." out of ".$games." games.";
                                $db->exec( "INSERT INTO ".OSDB_BANS." (botid,server,name,ip,gamename,date,admin,reason) VALUES ('1', '$realm', '$lname', '$IPaddress', '$gamename', CURRENT_TIMESTAMP(), 'Grief-Ban', '$reason')" );
				$BANNED = 1;
                        //Check players with lower games than 10 for a high amount of leaving (over or 6 out of 10 games is to much)
                        }
			if( $games <= 10 AND $leave_count >= 6  AND $is_admin == "0" AND $is_safe == "0" AND $alreadybanned == 0 AND $BANNED == 0 ) {
                                $reason = "AUTOBAN: Player left ".$leave_count." out of ".$games." games.";
                                $db->exec( "INSERT INTO ".OSDB_BANS." (botid,server,name,ip,gamename,date,admin,reason) VALUES ('1', '$realm', '$lname', '$IPaddress', '$gamename', CURRENT_TIMESTAMP(), 'Grief-Ban', '$reason')" );
                                $BANNED = 1;
                        }
                        //Check players with more than 10 games, only 10% is a accepted amount of leaving
                        if( $games > 15 AND ( $leaveratio > 15 ) AND $is_admin == "0" AND $is_safe == "0" AND $alreadybanned == 0 AND $BANNED == 0 ) {
                                $reason = "AUTOBAN: Player left has a leaving ratio of ".$leaveratio."% out of ".$games." games.";
                                $db->exec( "INSERT INTO ".OSDB_BANS." (botid,server,name,ip,gamename,date,admin,reason) VALUES ('1', '$realm', '$lname', '$IPaddress', '$gamename', CURRENT_TIMESTAMP(), 'Grief-Ban', '$reason')" );
                                $BANNED = 1;
                        }
                        //Now check for a high amount of disconnects, they could be done on purpose!
                        if( $dcratio > 20 AND $games > 20 AND $is_admin == "0" AND $is_safe == "0" AND $alreadybanned == 0 AND $BANNED == 0 ) {
                                $reason = "AUTOBAN: Player has a disconnect ratio of ".$dcratio."% out of ".$games." games.";
                                $db->exec( "INSERT INTO ".OSDB_BANS." (botid,server,name,ip,gamename,date,admin,reason) VALUES ('1', '$realm', '$lname', '$IPaddress', '$gamename', CURRENT_TIMESTAMP(), 'Grief-Ban', '$reason')" );
                                $BANNED = 1;
                        }
		
		$result2 = $db->prepare("SELECT player, streak, maxstreak, losingstreak, maxlosingstreak, double_score, `score`, `score2`, games, draw
		FROM ".OSDB_STATS." WHERE (player) = ?");
		$result2->bindValue(1, strtolower( trim($name) ), PDO::PARAM_STR);
		$result = $result2->execute(); 
		if ($result2->rowCount() >=1) {
        $stats = $result2->fetch(PDO::FETCH_ASSOC);
		$streak = $stats["streak"];
		$maxstreak = $stats["maxstreak"];
		$losingstreak = $stats["losingstreak"];
		$maxlosingstreak = $stats["maxlosingstreak"];
                $is_double = $stats["double_score"];
		$CurrentScore = $stats["score"];
                $CurrentScore2 = $stats["score2"];
		$user_games = $stats["games"];
		$user_draw = $stats["draw"];
		} else {
		  $streak = 0; $maxstreak = 0; $losingstreak = 0; $maxlosingstreak = 0; $is_double = 0; $CurrentScore = $ScoreStart; $user_games = 0; $user_draw = 0; $CurrentScore2 = $ScoreStart;
		}
		//AVGCALC
		$curscore = $CurrentScore2;
		if( $user_games > 10 ) {
			if( $ScoreStart != 0 ) $curscore = $CurrentScore2 - $ScoreStart;
			$avgscore = $curscore / ( $user_games - $user_draw );
		} else {
			$avgscore = 0;
		}

		//Check if Rooadmin
		
		$listofroots = explode( ",", $RootAdmins );
		foreach( $listofroots as $root ) {
			if( strtolower($root) == strtolower($name) ) $is_admin = 2;
		}
		

		//WIN STREAK
		//increase maxstreak until lose.
		if ($winner == 1) {
		$streak = $streak+1; 
		if ( $streak > $maxstreak ) $maxstreak = $maxstreak+1;
		} 
		if ($winner == 0) $streak = 0;
		//if player lose, reset streak.
		
		//LOSING STREAK
		//increase maxstreak until win.
		if ($winner == 0) {
		$losingstreak = $losingstreak+1; 
		if ( $losingstreak > $maxlosingstreak ) $maxlosingstreak = $maxlosingstreak+1;
		} 
		if ($winner == 1) $losingstreak = 0;
		//if player win, reset streak.
		
		if ( $deaths == 0 AND $draw!=1 ) $zerodeaths = 1; else $zerodeaths = 0;
		
		//Create a new player...
		  if ( $result2->rowCount() <=0) {
		  if ( $is_double == 1 ) $score = $score*2;
          $sql3 = "INSERT INTO ".OSDB_STATS."(player, player_lower, score, score2, games, wins, losses, draw, kills, deaths, assists, creeps, denies, neutrals, towers, rax, banned, ip, warn_expire, warn, admin, safelist, realm, reserved, leaver, streak, maxstreak, losingstreak, maxlosingstreak, zerodeaths, dc_count ) 
		  VALUES('".$name."', '".strtolower( trim($name))."', '$score', '$realscore', '1', $winner, $loser, $draw, $kills, $deaths,$assists, $creepkills, $creepdenies, $neutralkills, $towerkills, $raxkills, $BANNED, 
		  '$IPaddress', '$warn_expire', '$warn', '$is_admin', '$is_safe', '$realm', '$reserved', '$leaver', '$streak', '$maxstreak', '$losingstreak', '$maxlosingstreak', '$zerodeaths', '$dc')";

          } else {
		  //...or update player data
		  if ($winner == 1  AND $leaver == 0) $score = "score = score + $ScoreWins,";
		  if ($winner == 1) $realscore = "score2 = score2 + $ScoreWins,";
		  if ($winner == 1 AND $is_double == 1  AND $leaver == 0 ) $score = "score = score + ".($ScoreWins*2).",";
		  if ($winner == 0 AND $leaver == 0) $score = "score = score - $ScoreLosses,";
                  if ($winner == 0) $realscore = "score2 = score2 - $ScoreLosses,";
		  if ($win==0) { $score = ""; $leaver = 0; }
		  

		  //LEAVER
		  if ( ( $list["left"] <= ($list["duration"] - $LeftTimePenalty) ) AND $list["leftreason"] == "has left the game voluntarily"  AND $leaver == 0 ) {
		  $score = "score = score - $ScoreDisc,";
		  $winner = 0;
		  $loser = 0;
		  }
		  
		  $sql3 = "UPDATE ".OSDB_STATS." SET 
		  $score
		  $realscore
		  avg_score = ".$avgscore.",
		  player = '".$name."',
		  player_lower = '".strtolower( trim($name))."',
		  games = games+1, 
		  wins = wins +$winner,
		  losses = losses+$loser,
		  draw = draw + $draw,
		  kills = kills + $kills,
		  deaths = deaths + $deaths,
		  assists = assists + $assists,
		  creeps = creeps + $creepkills,
		  denies = denies + $creepdenies,
		  neutrals = neutrals + $neutralkills,
		  towers = towers + $towerkills,
		  rax = rax + $raxkills,
          	  banned = $BANNED,
		  ip = '$IPaddress',
		  warn_expire = '$warn_expire',
		  $warn_qry
		  admin = '$is_admin',
		  safelist = '$is_safe',
		  realm = '$realm',
		  reserved = reserved + $reserved,
		  leaver = leaver + $leaver,
		  streak = '$streak',
		  maxstreak = '$maxstreak',
		  losingstreak = '$losingstreak',
		  maxlosingstreak = '$maxlosingstreak',
		  zerodeaths = zerodeaths + $zerodeaths,
                  dc_count = dc_count + $dc
		  WHERE (player) = ('".$name."');";
		   }
		  $result = $db->exec($sql3);
		  //$result = $UpdateResult->execute();  
		//OS_UpdateScoresTable( $name  );
		
		
		 }
		 //$return.="\nGame ($gid) updated!";
	     //Update "games" table so we can know what games have been updated
	     $update = $db->prepare("UPDATE ".OSDB_GAMES." SET stats = 1 WHERE id = '".$gid."' ");
		 $result = $update->execute();  
	   }
          if ($temp_points>=1) {
           $updateBP = $db->prepare("UPDATE ".OSDB_STATS." SET best_player = best_player+1 WHERE LOWER(player) = LOWER('".$BestPlayer."') ;");
	   $result = $updateBP->execute();
          }
	   $return.="\nGame ($gid) updated! ".$recentstayedplayers." players";
	 }
	 usleep(0.5*100000);
	 flush();
	}
	
if (isset($_GET["reset"])) {
   header("location: ".OS_HOME."adm/update_stats.php"); die;
}

	$sth = $db->prepare( "SELECT COUNT(*) FROM ".OSDB_GAMES." 
	WHERE (map) LIKE ('%".$MapString."%') AND stats = 0 AND duration>='".$MinDuration."'" );
	$result = $sth->execute(); 
    $r = $sth->fetch(PDO::FETCH_NUM);
    $TotalGamesForUpdate = $r[0];
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<?php if (isset($_GET["refresh"]) AND is_numeric($_GET["refresh"]) AND $TotalGamesForUpdate>=1) {  ?>
    <meta http-equiv="refresh" content="<?=(int) $_GET["refresh"]?>" />
<?php } ?>
<?php if (isset($_GET["reset"])) {  ?>
    <meta http-equiv="refresh" content="2" />
<?php } ?>
 	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta http-equiv="content-style-type" content="text/css" />
	<meta name="author" content="Ivan Antonijevic" />
	<meta name="rating" content="Safe For Kids" />
 	<meta name="description" content="<?=$HomeDesc?>" />
	<meta name="keywords" content="<?=$HomeKeywords?>" />
	<title><?=$HomeTitle?></title>
	<link rel="stylesheet" href="<?=OS_HOME?>themes/blogger/style.css" type="text/css" />
	
</head>
  
<body>

<div id="wrapper" style="height:860px">
<div id="logo">
  <h1><?=$HomeTitle?></h1>
</div>  

 <?php include("admin_menu.php"); ?>

<div align="center" style="background-color: #fff; width: 960px; margin: 0 auto; padding-top: 18px; padding-bottom: 28px; border: 10px solid #2B0202; border-radius: 10px;">
   <?php if ($TotalGamesForUpdate>=1) { ?>
   Unranked Games: <?=$TotalGamesForUpdate?>
   <a class="menuButtons" href="<?=OS_HOME?>adm/update_stats.php?start&amp;refresh=3">Update ALL</a>
   <a class="menuButtons" href="<?=OS_HOME?>adm/update_stats.php?reset">Reset ALL stats</a>
   <?php
   if ( isset($return)  AND !empty($return) ) { ?>
   <div style="margin-top: 16px;">
   <textarea style="width: 400px; height: 290px;"><?=$return?></textarea>
   </div>
   <?php if (isset($_GET["refresh"]) AND is_numeric($_GET["refresh"]) AND isset($_GET["start"]) AND $TotalGamesForUpdate>=1) {  ?>
   <h2>Please wait...updating stats</h2>
   <?php } ?>
   <?php } ?>
   <?php } else { ?>
   <div>Unranked Games: <?=$TotalGamesForUpdate?></div>
   <h2>There is no games for update.</h2>
   <div class="padTop"></div>
   <a class="menuButtons" href="<?=OS_HOME?>adm/update_stats.php?reset">Reset ALL stats</a>
   <?php } ?>
</div>

</div>

<div style="margin-top: 20px;">&nbsp;</div>
<?php
      include('../themes/'.$DefaultStyle.'/footer.php');
 }
?>
