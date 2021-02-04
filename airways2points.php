<?php
include("security.php");
$security = new SECURITY();
$security->parse_incoming();
if(rawurldecode($_GET['route'])=="LOCAL"){
    exit("{\"code\":200,\"msg\":\"\",\"content\":[]}");
}
$db_name = './NavData/CHN2014001.rom';
$conn = new SQLite3($db_name);
$route = explode(" ",rawurldecode($_GET['route']));
$prewp = '';
$wpfromenroute = [];
function search_waypoint($ident,$ICAO_CODE,$SECT_CODE){
    $result=[];
    global $conn;
    $sql="select * from WAYPOINT where WAYPOINT_IDENT='$ident' AND WAYPOINT_ICAO_CODE='$ICAO_CODE' AND SECT_CODE='$SECT_CODE'";
    $ret = $conn->query($sql);
    $res = $ret->fetchArray(SQLITE3_ASSOC);
    if($res!=[]){
        $result['IDENT'] = $res['WAYPOINT_IDENT'];
        $result['LAT']=$res['WAYPOINT_LAT'];
        $result['LON']=$res['WAYPOINT_LON'];
        return $result;
    }else{
        $sql="select * from VHF_NAVAID where VOR_IDENT='$ident' AND VHF_ICAO_CODE='$ICAO_CODE' AND SECT_CODE='$SECT_CODE'";
        $ret = $conn->query($sql);
        $res = $ret->fetchArray(SQLITE3_ASSOC);
        if($res!=[]){
            
            $result['IDENT'] = $res['VOR_IDENT'];
            $result['LAT']=$res['VOR_LAT'];
            $result['LON']=$res['VOR_LON'];
            return $result;
        }else{
            $sql="select * from NDB_NAVAID where NDB_IDENT='$ident' AND NDB_ICAO_CODE='$ICAO_CODE' AND SECT_CODE='$SECT_CODE'";
            $ret = $conn->query($sql);
            $res = $ret->fetchArray(SQLITE3_ASSOC);
            if($res!=[]){
                $result['IDENT'] = $res['NDB_IDENT'];
                $result['LAT']=$res['NDB_LAT'];
                $result['LON']=$res['NDB_LON'];
                return $result;
            }
        }
    }
}
function getmindiswp($ident){
    global $conn,$prewp;
    $minndis = 2147483647;
    $minnwp = [];
    $sql="select * from WAYPOINT where WAYPOINT_IDENT='".$ident."'";
    $ret = $conn->query($sql);
    while($res = $ret->fetchArray(SQLITE3_ASSOC)){
        if(getdistanceAction($res['WAYPOINT_LAT'],$res['WAYPOINT_LON'],$prewp['LAT'],$prewp['LON'])<$minndis){
            $minndis = getdistanceAction($res['WAYPOINT_LAT'],$res['WAYPOINT_LON'],$prewp['LAT'],$prewp['LON']);
            $minnwp['FIX_IDENT'] = $res['WAYPOINT_IDENT'];
            $minnwp['FIX_ICAO_CODE']=$res['WAYPOINT_ICAO_CODE'];
            $minnwp['FIX_SECT_CODE']=$res['SECT_CODE'];
        }
    }

    $sql="select * from VHF_NAVAID where VOR_IDENT='".$ident."'";
    $ret = $conn->query($sql);
    while($res = $ret->fetchArray(SQLITE3_ASSOC)){
        if(getdistanceAction($res['VOR_LAT'],$res['VOR_LON'],$prewp['LAT'],$prewp['LON'])<$minndis){
            $minndis = getdistanceAction($res['VOR_LAT'],$res['VOR_LON'],$prewp['LAT'],$prewp['LON']);
            $minnwp['FIX_IDENT'] = $res['VOR_IDENT'];
            $minnwp['FIX_ICAO_CODE']=$res['VOR_ICAO_CODE'];
            $minnwp['FIX_SECT_CODE']=$res['SECT_CODE'];
        }
    }
    $sql="select * from NDB_NAVAID where NDB_IDENT='".$ident."'";
    $ret = $conn->query($sql);
    while($res = $ret->fetchArray(SQLITE3_ASSOC)){
        if(getdistanceAction($res['NDB_LAT'],$res['NDB_LON'],$prewp['LAT'],$prewp['LON'])<$minndis){
            $minndis = getdistanceAction($res['NDB_LAT'],$res['NDB_LON'],$prewp['LAT'],$prewp['LON']);
            $minnwp['FIX_IDENT'] = $res['NDB_IDENT'];
            $minnwp['FIX_ICAO_CODE']=$res['NDB_ICAO_CODE'];
            $minnwp['FIX_SECT_CODE']=$res['SECT_CODE'];
        }
    }
    return $minnwp;
}
function getdistanceAction($lat1,$lng1,$lat2,$lng2)
{
    $EARTH_RADIUS = 6378137;
    $RAD = pi() / 180.0;
    $radLat1 = $lat1 * $RAD;
    $radLat2 = $lat2 * $RAD;
    $a = $radLat1 - $radLat2;
    $b = ($lng1 - $lng2) * $RAD;
    $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)));
    $s = $s * $EARTH_RADIUS;
    $s = round($s * 10000) / 10000;
    return $s;
}
$prewp = [];
$sql = "SELECT * FROM AIRPORT WHERE ARPT_IDENT='".$_GET['DEPARTURE']."'";
$ret = $conn->query($sql);
$dep = $ret->fetchArray(SQLITE3_ASSOC);
$sql = "SELECT * FROM AIRPORT WHERE ARPT_IDENT='".$_GET['ARRIVE']."'";
$ret = $conn->query($sql);
$arr = $ret->fetchArray(SQLITE3_ASSOC);
$prewp['IDENT'] = $dep['IDENT'];
$prewp['LAT'] = $dep['ARPT_LAT'];
$prewp['LON'] = $dep['ARPT_LON'];
//echo $_GET['route'];
for($i=0;$i<sizeof($route)-1;$i = $i+2){
    $wps = [];
    if($route[$i+1]=="DCT"){
        if($wpfromenroute==[]){
            $res = getmindiswp($route[$i]);
            if($res==[]){
                exit("{\"code\":500,\"msg\":\"ERROR AT ".$route[$i+2]."\",\"content\":[]}");
            }else{
                $wpfromenroute[] = $res;
            }
        }
        $res = getmindiswp($route[$i+2]);
        if($res==[]){
            exit("{\"code\":500,\"msg\":\"ERROR AT ".$route[$i+2]."\",\"content\":[]}");
        }else{
            $wpfromenroute[] = $res;
        }
    }else{
        $sql = "SELECT FILE_RECD_NR FROM ENROUTE_AIRWAYS WHERE ROUTE_IDENT='".$route[$i+1]."' AND FIX_IDENT='".$route[$i]."'";
        $ret = $conn->query($sql);
        $p1nr = $ret->fetchArray(SQLITE3_ASSOC)['FILE_RECD_NR'];
        if($p1nr==NULL){
            exit("{\"code\":500,\"msg\":\"ERROR AT ".$route[$i]."\",\"content\":[]}");
        }
        $sql = "SELECT FILE_RECD_NR FROM ENROUTE_AIRWAYS WHERE ROUTE_IDENT='".$route[$i+1]."' AND FIX_IDENT='".$route[$i+2]."'";
        $ret = $conn->query($sql);
        $p2nr = $ret->fetchArray(SQLITE3_ASSOC)['FILE_RECD_NR'];
        if($p2nr==NULL){
            exit("{\"code\":500,\"msg\":\"ERROR AT ".$route[$i+2]."\",\"content\":[]}");
        }
        $sql = "SELECT *
        FROM ENROUTE_AIRWAYS
        WHERE ROUTE_IDENT='".$route[$i+1]."' AND FILE_RECD_NR BETWEEN ".min($p1nr,$p2nr)." AND ".max($p1nr,$p2nr);
        $ret = $conn->query($sql);
        while($row = $ret->fetchArray(SQLITE3_ASSOC)){
            $wps[] = $row;
        }
        if($p2nr<$p1nr){
            $wps = array_reverse($wps);
        }
        if($wpfromenroute!=[]){
            unset($wps[0]);
        }
        $prewp = $wps[sizeof($wps)-1];
        $wpfromenroute = array_merge($wpfromenroute,$wps);
    }
}
$rewp = [];
foreach($wpfromenroute as $wp){
    $rewp[] = search_waypoint($wp['FIX_IDENT'],$wp['FIX_ICAO_CODE'],$wp['FIX_SECT_CODE']);
}
$retu['code']=200;
$retu['msg']="";
$retu['content'] = $rewp;
echo json_encode($retu);
?>