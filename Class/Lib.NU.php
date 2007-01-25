<?php

function getDocFromSid($sid) {
}

function createADUser($sid) {
}


function createADGroup($sid) {
}

/**
 * return LDAP AD information from SID
 * @param string $sid ascii sid
 * @param array &$info ldap information
 * @return string error message - empty means no error
 */
 function getAdInfoFromSid($sid,&$info) {
   $hex='\\'.substr(strtoupper(chunk_split(bin2hex(sid_encode($sid)),2,'\\')),0,-1);
   print "[$hex]";
   $err=getADUser($hex,$info,"objectsid");
   return $err;  
}
/**
 * return LDAP AD information from the $login
 * @param string $login connection identificator
 * @param array &$info ldap information
 * @return string error message - empty means no error
 */
function getADUser($login,&$info,$ldapbindloginattribute="sAMAccountName") {
  include_once("AD/Lib.AD.php");
  $ldaphost=getParam("AD_HOST");
  $ldapbase=getParam("AD_BASE");
  $ldappw=getParam("AD_PASSWORD");
  $ldapbinddn=getParam("AD_BINDDN");


  $info=array();

  $ds=ldap_connect($ldaphost);  // must be a valid LDAP server!

  if ($ds) {
    $r=ldap_bind($ds,$ldapbinddn,$ldappw);  

    // Search login entry
    $sr=ldap_search($ds, "$ldapbase", "$ldapbindloginattribute=$login"); 

    $count= ldap_count_entries($ds, $sr);
    if ($count==1) {
      $info1 = ldap_get_entries($ds, $sr);
      $info0= $info1[0];
      $entry= ldap_first_entry($ds, $sr);
      //      print "<pre>";print_r($info);print "</pre>";
      foreach ($info0 as $k=>$v) {
	if (! is_numeric($k)) {
	  //print "$k:[".print_r2(ldap_get_values($ds, $entry, $k))."]";
	  if ($k=="objectsid") {
	    // get binary value from ldap and decode it
	    $values = ldap_get_values_len($ds, $entry,$k);	   
	    $info[$k]=sid_decode($values[0]);
	  } else {
	    if ($v["count"]==1)  $info[$k]=$v[0];
	    else {
	      //	    unset($v["count"]);
	      if (is_array($v))  unset($v["count"]);   
	      $info[$k]=$v;
	    }
	  }
	}
      }
      
    } else {
      if ($count==0) $err=sprintf(_("Cannot find user [%s]"),$login);
      else $err=sprintf(_("Find mutiple user with same login  [%s]"),$login);
    }

    
    ldap_close($ds);

  } else {
    $err=sprintf(_("Unable to connect to LDAP server %s"),$ldaphost);
  }

  return $err;
  
}
/**
 * encode Active Directory session id in binary format
 * @param string $sid
 * @return data the binary id
 */
function sid_encode($sid) {
  $osid=false;
  if (!$sid) return false;
  $n232=pow(2,32);
  $tid=explode('-',$sid);
  
  $number=count($tid)-4;
  $tpack["rev"]=sprintf("%02d",intval($tid[1]));
  $tpack["b"]=sprintf("%02d",5); // always 5
  if (floatval($tid[2]) >= $n232) {    
    $tpack["c"]=intval(floatval($tid[2])/$n232);
    $tpack["d"]=intval(floatval($tid[2])-floatval($tpack["c"])*$n232);
  } else {
    $tpack["c"]=0;
    $tpack["d"]=$tid[2];
  }
  for ($i=0;$i<$number;$i++) {    
    $tpack["e".($i+1)]=floatval($tid[$i+3]);
  }

  print_r($tpack);
  if ($number==5) 
  $osid=pack("H2H2nNV*",$tpack["rev"],$tpack["b"],$tpack["c"],$tpack["d"],
	  $tpack["e1"],$tpack["e2"],$tpack["e3"],$tpack["e4"],$tpack["e5"] );

  if ($number==2) 
    $osid=pack("H2H2nNV*",$tpack["rev"],$tpack["b"],$tpack["c"],$tpack["d"],
	       $tpack["e1"],$tpack["e2"] );
  return $osid;
}

/**
 * Decode Active Directory session id in ascii format
 * @param data $osid the binary session id
 * @return string the ascii id (false if error)
 */
function sid_decode($osid) {
  $sid=false;
  if (!$osid) return false;
  $u=unpack("H2rev/H2b/nc/Nd/V*e", $osid);
  print_r2($u);
  if ($u) {
    $n232=pow(2,32);
    unset($u["b"]);
    $u["c"]= $n232*$u["c"]+$u["d"];
    unset($u["d"]);

    $sid="S";
    foreach ($u as $v) {
      if ($v < 0) $v=$n232 + $v;
      $sid.= "-".$v;
    }
  }
  return $sid;
}
?>
