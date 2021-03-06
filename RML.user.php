<?php

// ============================================================================
//  User functions for Radical Militant Library
//  Copyright (C) 2009-2015 Jotunbane 
//
//  This program is free software; you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation; either version 2 of the License, or
//  (at your option) any later version.
//
//  This program is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
// ============================================================================

function RMLgetcurrentuser()
{
	global $cookie;

	if( $cookie ) {
		list( $thisuser, $cookie_hash ) = preg_split( '@,@', $cookie );
		if ( getPwdHash( $secret_salt . $thisuser . $secret_salt ) == $cookie_hash ) {
			return $thisuser;
		} else {
			return null;
		}
	}
	return null;
}

// ============================================================================

function RMLlogin()
{
	global $username, $password;

	$id = ( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) ? $_GET['id'] : 0 ;

	if ( RMLvalidateuser( $username, $password ) ) {
		setcookie( 'RML', $username .',' .getPwdHash( $secret_salt . $username . $secret_salt) );
	} else {
		unset( $username );
		unset( $password );
	}

//	if ( $id !== 0 ) {
		header( 'Location: ?function=user' );
//	} else {
//		header( 'Location: ?document=view&id='.$id );
//	}
}

// ============================================================================

function RMLlogout()
{
	setcookie( 'RML', '', time() - 86400 );
}

// ============================================================================

function RMLvalidateuser($username,$password)
{
	$result = RMLfiresql("SELECT pass FROM \"user\" WHERE handle='$username'");
	$thisrow = pg_Fetch_Object($result,0);
	$thispass = $thisrow->pass;

	if($thispass == getPwdHash($password)) {
		return true;
	} else {
		return false;
	}
}

// ============================================================================

function RMLdisplaysignup( $print_on = true ) {
	$out = "\n".'<div class="box"><div class="boxheader"><b>Login</b></div>
<div class="boxtext"><strong>Take care, where you login!</strong>'

/* at least visual hint at login */
.'<div style="margin-top: 1ex;"><img src="./img/proxypic.png" alt="do not use fake onions: c3jembnkdnbcdniu !" ></div>'

.'<table><form method="post" action="?function=login"><input type="hidden" name="id" value="' .$_GET['id'] .'"><fieldset>
<tr><td>Handle </td><td>: <input type="text" size="40" name="username" /></td></tr>
<tr><td>Password </td><td>: <input type="password" size="40" name="password" /></td></tr>
<tr><td></td><td><input class="formbutton" type="submit" value="Log in" /></td></tr>
</fieldset></form></table></div></div>

<div class="box"><div class="boxheader"><b>Sign Up</b></div>
<div class="boxtext">'."Thank You for your interest in Radical Militant Library.<br/> This page is very temporary, and as such may not be all that user friendly.<br/>Handle' must be unique.<br/>'E-mail' can be anything. Your email is ONLY used on the books that you upload, if you don't want your email show here then please leave this field blank.".'<br/><br/><b><big>I CAN NOT RETRIEVE LOST PASSWORDS</big></b>
<table><form method="post" action="?function=newuser"><input type="hidden" name="id" value="' .$_GET['id'] .'"><fieldset>
<tr><td>Handle </td><td>: <input type="text" size="30" name="username"/></td></tr>
<tr><td>E-mail </td><td>: <input type="text" size="30" name="mail"/></td></tr>
<tr><td>Password </td><td>: <input type="password" size="30" name="password"/></td></tr>
<tr><td>Password&nbsp;again </td><td>: <input type="password" size="30" name="password2"/></td></tr>
<tr><td></td><td><input class="formbutton" type="submit" value="Sign Up"/></td></tr>
</fieldset></form></table></div></div>';
	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLcreatenewuser()
{
	$welcomemessage = "Welcome to Radical Militant Library. This message is a placeholder for a more informative message coming sometime in the future.<br><br>This service is not yet ready, so dont expect anything to work, but feel free to snoop around.";

	global $username, $mail, $password, $password2;

	$id = ( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) ? $_GET['id'] : 0 ;

	$result = RMLfiresql("SELECT * FROM \"user\" WHERE handle='$username'");
	if( pg_numrows( $result ) > 0 ) {
		unset( $username );
	} else {
		$result = RMLfiresql("SELECT email FROM \"user\" WHERE email='$mail'");
	}

	if( !isset( $username ) || pg_numrows( $result ) > 0 || $password !== $password2 ) {
		header( 'Location: ?function=login' .( ( $id !== 0 ) ? '&id='.$id : '' ) );
	}

	unset( $password2 );

	RMLfiresql("INSERT INTO \"user\" (id,handle,email,pass) VALUES(DEFAULT,'$username','$mail','" .getPwdHash( $password ) ."')");
	RMLsendmessage( $username, $welcomemessage, 'SYSTEM', 'Welcome '.$username, true );

	RMLlogin();
}

// ============================================================================

/* vv01f: change password method in a single function
 * */
function getPwdHash( $password )
{
	return sha1($password);
}

// ============================================================================

function RMLdisplayuserpage( $print_on = true ) {
	$out = "<div class=\"order\"><a href=\"./?function=logout\" class=\"button delete\">Log-out</a></div>"
	.RMLdisplaydocuments( false )
	.RMLdisplaystylesheets( false )
	.RMLdisplaymessages( false )
	.RMLdisplaychpwd( false );
	return processOutput( $out, $print_on );
}

// ============================================================================

/* vv01f: Password Change Day: 1st of Feb ... let's go for it in 2016 ! as J. called the feature recently
 * and sha1 was encapsuled in a single function where it might be changed in another commit
 * 
 * also see: https://www.owasp.org/index.php/Password_length_%26_complexity
 * 
 * */
function RMLdisplaychpwd( $print_on = true )
{
	$user = RMLgetcurrentuser();
	if ( !isset( $user ) || $user === '' ) {
		return false;
	}

	define( CHANGE_PASSWORD_SUBMIT, 'Change Password' );
	$err = '';
	$out = '';

	//$policy=getPasswordPolicy();
	//maybe later enforce less weak passwords, as there are such around like of numbers only or very short single words or names (sic!)
	//e.g. look for most used passwords and make a blacklist

	//process data if form was sent
	if ( $_POST['submit'] === CHANGE_PASSWORD_SUBMIT ) {

		//validate input
		$pwd0 = ( isset( $_POST['password0'] ) ) ? $_POST['password0'] : '';
		$pwd1 = ( isset( $_POST['password1'] ) ) ? $_POST['password1'] : '';
		$pwd2 = ( isset( $_POST['password2'] ) ) ? $_POST['password2'] : '';

		//check authentication on known errors
		if ( $pwd0 === '' || !RMLvalidateuser( $user, $pwd0 ) ) {
			$err .= 'Error in old password.<br>';
		} elseif ( $pwd1 === '' ||  $pwd2 === '' ||  $pwd2 !== $pwd1 ) {
			$err .= 'Error in new password<br>';
		} /*elseif () {	//maybe apply $policy
		}/**/
		
		//set new pass as long as no errors occured
		if ( $err === '' ) {
			$result = RMLfiresql( "UPDATE \"user\" SET pass='" .getPwdHash( $pwd1 ) ."' WHERE handle='".$user."'" );
			if ( !$result ) {
				$err .= 'Error on password change.<br>';
			} else {
				$out .= 'Password successfully changed.<br>';
			}
		}

	} else {

		//or deploy form for data
		$out .= "\n" .'<form method="post" name="chpwd" id="chpwd" action="?function=user">
<table class="form">
<tr><td valign="top">Old Password:</td>
<td><input class="norm password" type="password" name="password0" id="password0" value=""></td></tr>
<tr><td valign="top">New Password:</td>
<td><input class="norm password" type="password" name="password1" id="password1" value=""></td></tr>
<tr><td valign="top">New Password again:</td>
<td><input class="norm password" type="password" name="password2" id="password2" value=""></td></tr>
<tr><td></td><td><input id="submit" name="submit" type="submit" value="' .CHANGE_PASSWORD_SUBMIT .'"></td></tr>
</table>
</form>';

	}
/* ewa: playing with css to find a solution for toggling sections with the state stored in a checkbox #stubb.cm:checked {} */
	$out = "\n" .'<div class="box" id="change-password">
<div class="boxheader">'
/*.'<!--
<label for="stubb-cb">+</label>
<input style="position: absolute;left: -9000em;top: auto;overflow: hidden;" type="checkbox" name="stubb-cb" id="stubb-cb">
-->'/**/
.'<b>User Stuff</b></div>'
/*.'<!--
<div class="toggleitem">
-->'/**/
.'<div class="boxtext">' .( ( $err === '' ) ? $out : $err ) .'</div>'
.RMLdisplayavatar( false ) .'</div>'
/*.'<!--
</div>
-->'/**/
;
	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLdisplaystylesheets( $print_on = true ) {
	$user = RMLgetcurrentuser();
	$result = RMLfiresql("SELECT id,name FROM stylesheet WHERE owner='$user' ORDER BY id");

	$out = "\n".'<div class="box">
<div class="boxheader"><b>Stylesheets</b></div>
<div class="boxtext">';
	for( $row=0; $row < pg_numrows( $result ); $row++ ) {
		$thisrow = pg_Fetch_Object( $result, $row );
		$id = $thisrow->id;
		$stylename = $thisrow->name;
		$out .= '<b><a href="?style=edit&amp;id='.$id.'">'.$stylename.'</a></b><br/>';
	}
	$out .= "\n".'</div><p class="boxtext"><a class="button add" href="?style=new">New Stylesheet</a></p></div>';
	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLnewstylesheet( $print_on = true ) {
	$user = RMLgetcurrentuser();
	if( $user ) {
		
		$result = RMLfiresql("SELECT style FROM stylesheet WHERE owner='$user' AND name='default'");
		if(pg_numrows($result) > 0) {
			$thisrow = pg_Fetch_Object($result,0);
			$style = $thisrow->style;
			$name = "RENAME ME";
		} else {
			$result = RMLfiresql("SELECT style FROM stylesheet WHERE id=1");
			$thisrow = pg_Fetch_Object($result,0);
			$style = $thisrow->style;
			$style = "/* This is the Global 'Default' stylesheet. You can use that as a template.*/\n\n" . $style;
			$name = "default";
		}
		
		$out = "\n<form method=\"post\" action=\"?style=save\"><table class=\"form\">
<tr><td valign=\"top\"><b>Name:</b></td><td><input class=\"norm\" type=\"text\" name=\"title\" value=\"$name\"></td></tr>
<tr><td valign=\"top\"><b>Stylesheet:</b></td><td><textarea class=\"norm\" rows=\"20\" cols=\"41\" wrap=\"none\" name=\"body\">$style</textarea></td></tr>
<tr><td></td><td><input type=\"submit\" value=\"Save styleshit\"></td></tr></table></form>";
	} else {
		$out = "ERROR : New stylesheet, Cookie baaaaad......";
	}
	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLeditstylesheet($id, $print_on = true ) {
	$user = RMLgetcurrentuser();
	
	$result = RMLfiresql("SELECT owner,style,name FROM stylesheet WHERE id=$id");
	$thisrow = pg_Fetch_Object($result,0);
	$owner = $thisrow->owner;
	$style = $thisrow->style;
	$name = $thisrow->name;

	if( !hasRights( 'editstyle', array( $owner ) ) ) {
		$out = "ERROR : Edit Stylesheet, Cookie baaaaad.......";
	} elseif( ! $user ) {
		$out = "ERROR : New stylesheet, Cookie baaaaad......";
	} else {
		$out = "\n<form method=\"post\" action=\"?style=update&id=$id\"><table class=\"form\">
<tr><td valign=\"top\"><b>Name:</b></td><td><input class=\"norm\" type=\"text\" name=\"title\" value=\"$name\"></td></tr>
<tr><td valign=\"top\"><b>Stylesheet:</b></td><td><textarea class=\"norm\" rows=\"20\" cols=\"41\" wrap=\"none\" name=\"body\">$style</textarea></td></tr>
<tr><td></td><td><input type=\"submit\" value=\"Save styleshit\"></td></tr></table></form>";
	}
	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLsavestylesheet() {
	global $title, $body;

	$user = RMLgetcurrentuser();

	RMLfiresql("INSERT INTO stylesheet VALUES('$user','$body','$title',DEFAULT)");
}

// ============================================================================

function RMLupdatestylesheet( $id, $print_on = true ) {
	global $title, $body;

	$user = RMLgetcurrentuser();

	$result = RMLfiresql("SELECT owner FROM stylesheet WHERE id=$id");
	$thisrow = pg_Fetch_Object($result,0);
	$owner = $thisrow->owner;

	if( hasRights( 'editstyle', array( $owner ) ) ) {
		RMLfiresql("UPDATE stylesheet SET style='$body', name='$title' WHERE id=$id");
	} else {
		$out = "ERROR : Update Stylesheet, Cookie baaaaad........";
	}
	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLdisplaymessages( $print_on = true ) {
	$user = RMLgetcurrentuser();
	$result = RMLfiresql("SELECT id,posted_on,subject,sender_handle FROM message WHERE handle='$user' ORDER BY posted_on DESC");

	$numrows = pg_numrows($result) - 1;

	$out = "\n<div class=\"box\"><div class=\"boxheader\"><b>Messages</b></div>
<div class=\"boxtext\">";

	if( pg_numrows( $result ) > 0 ) {

		for( $row=0; $row < pg_numrows( $result ); $row++ ) {
			$thisrow = pg_Fetch_Object( $result, $row );
			$id = $thisrow->id;
			$subject = $thisrow->subject;
			$posted = $thisrow->posted_on;
			$posted = RMLfixdate( $posted );
			$sender = $thisrow->sender_handle;
			$out .= "\n<a href=\"?message=view&amp;id=$id\"><img style=\"width:36px;\" align=\"left\" alt=\"Comment\" src=\"./img/Messages.png\"/>
<b>$subject</b></a><br/><small><i>from</i>: <b>$sender</b>, <i>$posted</i></small>";
			if($row < $numrows) {
				$out .= "\n<div class=\"clear\"><hr class=\"forumseperator\" /></div>";
			}
		}
	}
	if( pg_numrows( $result ) !== 0 ) {
		$out .= "\n<br/>";
	}
	$out .= "\n</div><p class=\"boxtext\"><a class=\"button add\" href=\"?message=new\">New Message</a></p></div>";
	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLdisplaydocuments( $print_on = true ) {
	$user = RMLgetcurrentuser();

	$out = '';
	if( $user ) {	
		$result = RMLfiresql( "SELECT id,status,posted_on,title,subject_id,author_id,year,teaser FROM document WHERE handle='$user' AND status<3 ORDER BY title" );

	$out .= "\n<div class=\"box\"><div class=\"boxheader\"><b>Documents</b></div><div class=\"boxtext\">";

		$numrows = pg_numrows( $result ) - 1;
		for( $row=0; $row < pg_numrows( $result ); $row++ ) {
			$thisrow = pg_Fetch_Object( $result, $row );
			$id = $thisrow->id;
			$status = $thisrow->status;
			$posted = $thisrow->posted_on;
			$posted = RMLfixdate( $posted );
			$title = $thisrow->title;
			$year = $thisrow->year;
			$authorid = $thisrow->author_id;
			$authorname = RMLgetauthorname( $authorid );
			$subjectid = $thisrow->subject_id;
			$subjecttitle = RMLgetsubjecttitle( $subjectid );
			$thisteaser = $thisrow->teaser;

			if( strlen( $thisteaser ) > 300 ) {
				$thisteaser = substr( $thisteaser, 0, 300 ) .' ...';
				$thisteaser = strip_tags( $thisteaser );
			}

			$out .= "\n<div class=\"box\"><div class=\"boxheader\"><a href=\"?document=view&amp;id=$id\"><img class=\"Cover\" style=\"width:100px\" src=\"./covers/cover$id\" alt=\"Document cover\"/><b>$title</b></a></div>"
			."\n<div class=\"boxtext\"><small>by <a href=\"?author=view&amp;id=$authorid\">$authorname</a>, <b>$year</b> Created: <b>$posted</b> in <b><a href=\"?subject=view&amp;id=$subjectid\">$subjecttitle</a></b></small><br/>$thisteaser</div><div class=\"clear\"></div></div>";
		}
		$out .= "\n</div><p class=\"boxtext\"><a class=\"button add\" href=\"?document=new\">New Document</a></p></div>";
	} else {
		$out .= 'Displaydocuments : Bad user...';
		RMLlogout();//ensure cookie is unset
	}
	$out .= "\n</div></div>";
	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLgetreviewhandle( $thisid )
{
	$result = RMLfiresql("SELECT owner FROM subject WHERE id=(SELECT subject_id FROM document WHERE id=$thisid)");
	$thisrow = pg_Fetch_Object( $result, 0 );
	$thisowner = $thisrow->owner;

	return $thisowner;
}

// ============================================================================

function RMLdisplayavatar( $print_on = true )
{
	$user = RMLgetcurrentuser();

	if( !file_exists( './users/' .$user .'.png' ) ) {
		$image = 'Anonymous';
	} else {
		$image = $user;
	}

	$out = "\n" .'<div class="boxtext"><img style="float : left;margin: 0 1ex 1ex 0;border-style : solid; border-color : black; border-width : 1px" src="./users/' .$image .'.png">&nbsp;&nbsp;Please, no larger than a 96 x 96 PNG file.</div>
<div class="boxtext"><form enctype="multipart/form-data" method="post" action="?document=avatar">&nbsp;&nbsp;<input type="file" size="25" name="picture"><br/>&nbsp;&nbsp;<input type="submit" value="Change Avatar"><input type="hidden" name="document" value="avatar"></form></div>
<div class="clear"></div>';

	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLuploadavatar() {
	$handle = RMLgetcurrentuser();
	$target_path = "./users/" . "$handle" . ".png";
	move_uploaded_file($_FILES['picture']['tmp_name'], $target_path);
}

// ============================================================================

function RMLdisplaymessage( $id, $print_on = true ) {
	$result = RMLfiresql( "SELECT handle,body,posted_on,sender_handle FROM message WHERE id=$id" );
	$thisrow = pg_Fetch_Object( $result, 0 );
	$handle = $thisrow->handle;
	$body = nl2br($thisrow->body);
	$posted = $thisrow->posted_on;
	$posted = RMLfixdate( $posted );
	$sender = $thisrow->sender_handle;

	$out = '';
	if( hasRights( 'readmsg', array( $handle ) ) ) {
		$out .= "\n".'<img class="docicon" src="./users/' .$sender .'.png" />
From : <b>' .$sender.'</b><br/>Sent : <b>' .$posted.'</b>
<div class="inlineclear"></div>'
		.RMLdisplay( $body, 5, false )
		."\n".'<div class="bottom"><a class="button add" href="?message=
		&amp;id=' .$id.'">Reply</a>&nbsp;<a class="button delete" href="?message=delete&amp;id=' .$id.'">Delete</a></div>';
	} else {
		$out = "ERROR: Display Message : Cookiii baaaaaadddd...";
	}
	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLdeletemessage( $id ) {
	$result = RMLfiresql( "SELECT handle FROM message WHERE id=$id" );
	$thisrow = pg_Fetch_Object( $result, 0 );
	$handle = $thisrow->handle;

	if( hasRights( 'delmsg', array( $handle ) ) ) {
		RMLfiresql("DELETE FROM message WHERE id=$id");
	}

	header("Location: ?function=user");
}

// ============================================================================

function RMLreplymessage( $id, $print_on = true ) {
	$user = RMLgetcurrentuser();
	if( ( $user ) && ( $id ) ) {
		$result = RMLfiresql( "SELECT handle,sender_handle,body,subject FROM message WHERE id=$id" );
		$thisrow = pg_Fetch_Object( $result, 0 );
		$thishandle = $thisrow->handle;
		$thissender = $thisrow->sender_handle;
		$thisbody = htmlspecialchars($thisrow->body);
		$thissubject = $thisrow->subject;
		
		if( $thishandle <> $user ) {
			$out = "ERROR: Cookie Bad : Not your message??";
		} else {

			$options = '';
			$result2 = RMLfiresql( "SELECT handle FROM \"user\" ORDER BY handle" );
			for( $row=0; $row < pg_numrows( $result2 ); $row++ ) {
				$thisrow = pg_Fetch_Object( $result2, $row );
				$thisname = $thisrow->handle;

				if( $thisname == $thissender ) {
					$options .= "\n<option value=\"$thisname\" selected=\"yes\">$thisname</option>";
				} else if( in_array( $thisname, array( 'admin', 'SYSTEM'/** /, 'Anonymous'/**/ ) ) ) {
					$options .= "\n<option value=\"$thisname\">$thisname</option>";
				}
			}

			$out = "\n<form method=\"post\" action=\"?message=send\">
<table class=\"form\">
<tr><td><b>To : </b></td><td><select class=\"norm\" name=\"messageto\">"
			.$options
			."</select></td></tr>
<tr><td valign=\"top\"><b>Subject : </b></td><td><input class=\"norm\" type=\"text\" name=\"messagesubject\" value=\"Re: $thissubject\"></td></tr>
<tr><td valign=\"top\"><b>Message : </b></td><td><textarea class=\"norm\" rows=\"20\" cols=\"41\" wrap=\"none\" name=\"body\">".$thisbody."</textarea>
</td></tr><tr><td></td><td><input type=\"submit\" value=\"Send Reply\"></td></tr></table></form>";
		}
	}
	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLdisplaynewmessage( $print_on = true ) {
	$user = RMLgetcurrentuser();
	$out = '';
	if( hasRights( 'isuser' ) ) {
		$result = RMLfiresql("SELECT handle FROM \"user\" ORDER BY handle");

		if( !isset( $messageto ) || strlen( $messageto ) < 1 ) {
			$messageto = "Jotunbane";
		}
		$options_to = '';
		for( $row=0; $row < pg_numrows( $result ); $row++ ) {
			$thisrow = pg_Fetch_Object( $result, $row );
			$thisname = $thisrow->handle;

			$options_to .= "\n".'<option ';
			if( $thisname == $messageto ) {
				$options_to .= 'selected="yes" ';
			}
			$options_to .= 'value="'.$thisname.'">'.$thisname.'</option>';
		}
		$out .= "\n".'<form method="post" action="?message=send"><table class="form">
<tr><td><b>To : </b></td><td><select class="norm" name="messageto">'
				.$options_to
				."\n".'</select></td></tr>
<tr><td valign="top"><b>Subject : </b></td><td><input class="norm" type="text" name="messagesubject"></td></tr>
<tr><td valign="top"><b>Message : </b></td><td><textarea class="norm" rows="20" cols="41" wrap="none" name="body"></textarea>
</td></tr><tr><td></td><td><input type="submit" value="Send Message"></td></tr></table>
</form>';
	} else {
		$out = 'ERROR: You need to log in to send messages.';
	}
	return processOutput( $out, $print_on );
}

// ============================================================================

function RMLsendmessage( $to, $msg, $from = 'SYSTEM', $subj = 'Message', $print_on = true ) {
	$out = '';
	$r = RMLfiresql("INSERT INTO message (id,posted_on,handle,subject,body,sender_handle) VALUES (DEFAULT,NOW(),'$to','$subj','$msg','$from')");
	if( ! $r ) {
		$out = 'ERROR: Message not sent: FROM: '.$from.';TO:'.$to.'; SUBJ:'. $subj.'; MSG:'. $msg;
	}
	return processOutput( $out, $print_on );
}

/* ewa: return if currentuser has the right for the requested action */
function hasRights( $action = '', $arr = array() )
{// hasRights( 'addsubject', array( '' ) )
	$user = RMLgetcurrentuser();
	if ( $user == '' || $user == null ) {
		return false;
	}
	if ( ! in_array( 'noadm', $arr ) ) {
		$admaccs = array( 'admin', 'SYSTEM' );
		if ( ! in_array( 'nomod', $arr ) ) {
			$modaccs = array( 'Jotunbane', 'kittyhawk', 'ewa4boeker' );
		}
	}
	switch ( $action ) {
		case 'adddocument':	return true;//for now everyone; maybe later after creating a comment/post
			break;
		case 'addauthor' :	//after (5?) books
		case 'editauthor' :	//after (5?) book
		case 'addnews':		//after (10?) books
		case 'delnews':		//after (150?) books
		case 'editnews':	//on own books
		case 'test':		//
		case 'selfpublish':	//three main librarians for now, later after 50(?) books
			if ( in_array( $user, array_merge( $arr, $admaccs, $modaccs ) ) ) {
				return true;
			}
			break;
		case 'addsubject':
		case 'editsubject':
		case 'delmsg':
		case 'readmsg':
		case 'editstyle':
		case 'deldocument':
		case 'editdocument':
			if ( in_array( $user, array_merge( $arr, $admaccs ) ) ) {
				return true;
			}
		break;
		case 'isuser':
			return true;
		break;
	}
	return false;
}
