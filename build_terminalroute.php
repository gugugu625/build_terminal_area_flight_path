<?php
$airport = $_GET['airport'];
$type = $_GET['type'];
$proc_ident = $_GET['PROC_IDENT'];
$transition = $_GET['transition'];
$runway = $_GET['runway'];
$db_name = './NavData/CHN2014001.rom';
$conn = new SQLite3($db_name);
function getDegree($latA, $lonA, $latB, $lonB){
    $radLatA = deg2rad($latA);
    $radLonA = deg2rad($lonA);
    $radLatB = deg2rad($latB);
    $radLonB = deg2rad($lonB);
    $dLon = $radLonB - $radLonA;
    $y = sin($dLon) * cos($radLatB);
    $x = cos($radLatA) * sin($radLatB) - sin($radLatA) * cos($radLatB) * cos($dLon);
    $brng = rad2deg(atan2($y, $x));
    $brng = ($brng + 360) % 360;
    return $brng;
}
function getlatlonbyraddis($lat,$lon,$deg,$dis){
    $r = 6371393;
    $radlat = deg2rad($lat);
    $radlon = deg2rad($lon);
    $deg = deg2rad($deg);
    $lat2 = asin(sin($radlat)*cos($dis/$r)+cos($radlat)*sin($dis/$r)*cos($deg));
    $lon2 = $radlon+atan2(sin($deg)*sin($dis/$r)*cos($radlat),cos($dis/$r)-sin($radlat)*sin($lat2));
    $resl = [];
    $resl[] = rad2deg($lat2);
    $resl[] = rad2deg($lon2);
    return $resl;
}
function search_waypoint($ident,$ICAO_CODE,$SECT_CODE){
    $result=[];
    global $airport;
    global $conn;
    $sql="select * from WAYPOINT where WAYPOINT_IDENT='$ident' AND WAYPOINT_ICAO_CODE='$ICAO_CODE' AND SECT_CODE='$SECT_CODE' AND REGION_CODE='$airport'";
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
            }else{
                $sql = "select * from RUNWAY WHERE ARPT_IDENT='$airport' AND RUNWAY_IDENT='$ident'";
                $ret = $conn->query($sql);
                $res = $ret->fetchArray(SQLITE3_ASSOC);
                if($res!=[]){
                    $result['IDENT'] = $res['RUNWAY_IDENT'];
                    $result['LAT']=$res['RUNWAY_LAT'];
                    $result['LON']=$res['RUNWAY_LON'];
                    return $result;
                }else{
                    $sql="select * from WAYPOINT where WAYPOINT_IDENT='$ident' AND WAYPOINT_ICAO_CODE='$ICAO_CODE' AND SECT_CODE='$SECT_CODE'";
                    $ret = $conn->query($sql);
                    $res = $ret->fetchArray(SQLITE3_ASSOC);
                    if($res!=[]){
                        $result['IDENT'] = $res['WAYPOINT_IDENT'];
                        $result['LAT']=$res['WAYPOINT_LAT'];
                        $result['LON']=$res['WAYPOINT_LON'];
                        return $result;
                    }
                }
            }
        }
    }
}
function build_route($list,$runwaycon=[]){
    $lastwp = [];
    $result = [];
    if($runwaycon!=[]){
        $result[] = $runwaycon;
    }
    //var_dump($runwaycon);
    for($j=0;$j<sizeof($list);$j=$j+1){
        $row = $list[$j];
        //echo $j;
        switch($row['PATH_AND_TERMINATION']){
            case 'IF':
                $lastwp = $row;
                $result[] = search_waypoint($row['FIX_IDENT'],$row['FIX_ICAO_CODE'],$row['FIX_SECT_CODE']);
                break;
            case 'TF':
                $lastwp = $row;
                $result[] = search_waypoint($row['FIX_IDENT'],$row['FIX_ICAO_CODE'],$row['FIX_SECT_CODE']);
                break;
            case 'RF':
                $prewp = search_waypoint($lastwp['FIX_IDENT'],$lastwp['FIX_ICAO_CODE'],$lastwp['FIX_SECT_CODE']);
                $rfc = search_waypoint($row['CENTER_FIX_OR_TAA_PROCEDURE_TURN_IND'],$row['MULTIPLE_CODE_OR_TAA_SECTOR_ICAO_CODE'],$row['MULTIPLE_CODE_OR_TAA_SECTOR_SECT_CODE']);
                $towp = search_waypoint($row['FIX_IDENT'],$row['FIX_ICAO_CODE'],$row['FIX_SECT_CODE']);
                $startdeg = getDegree($rfc['LAT'],$rfc['LON'],$prewp['LAT'],$prewp['LON']);
                //echo $startdeg." ";
                $enddeg = getDegree($rfc['LAT'],$rfc['LON'],$towp['LAT'],$towp['LON']);
                //echo $enddeg." ".$row['TURN_DIR']."</br>";
                //if($row['FIX_IDENT']=='LZ316'){
                    //echo $startdeg." ".$enddeg;
                //}
                if($row['TURN_DIR']=="L"){
                    $startdeg = floor($startdeg)%360;
                    $enddeg = ceil($enddeg)%360;
                    $i = $startdeg;
                    $cnt = 0;
                    while($cnt<=360){
                        if($i==$enddeg){
                            break;
                        }
                        $huwp = getlatlonbyraddis($rfc['LAT'],$rfc['LON'],$i,($row['ARC_RADIUS']/1000)*1852);
                        $result[] = ['IDENT'=>"",'LAT'=>$huwp[0],'LON'=>$huwp[1]];
                        $i = $i-1;
                        if($i<0){
                            $i = 360+$i;
                        }
                        $cnt = $cnt+1;
                    }
                }else{
                    $startdeg = ceil($startdeg)%360;
                    $enddeg = floor($enddeg)%360;
                    $i = $startdeg;
                    $cnt = 0;
                    while($cnt<=360){
                        if($i==$enddeg){
                            break;
                        }
                        $huwp = getlatlonbyraddis($rfc['LAT'],$rfc['LON'],$i,($row['ARC_RADIUS']/1000)*1852);
                        $result[] = ['IDENT'=>"",'LAT'=>$huwp[0],'LON'=>$huwp[1]];
                        $i = $i+1;
                        if($i==360){
                            $i = 0;
                        }
                        $cnt = $cnt+1;
                    }
                }
                $result[] = $towp;
                $lastwp = $row;
                break;
            case 'DF':
                $lastwp = $row;
                $result[] = search_waypoint($row['FIX_IDENT'],$row['FIX_ICAO_CODE'],$row['FIX_SECT_CODE']);
                break;
            case 'CA':
                $startwp = $result[count($result)-1];
                $hdg = $row['MAG_COURSE'];
                //echo $hdg;
                $dis = ($row['ALT_1']/2500)*30;
                $turnwp = getlatlonbyraddis($startwp['LAT'],$startwp['LON'],$hdg,$dis*1852);
                $result[] = ['IDENT'=>"",'LAT'=>$turnwp[0],'LON'=>$turnwp[1]];
                break;
            case 'CF':
                $lastwp = $row;
                $result[] = search_waypoint($row['FIX_IDENT'],$row['FIX_ICAO_CODE'],$row['FIX_SECT_CODE']);
                break;
            default:
                if($row['FIX_LAT']!=""&&$row['FIX_LON']!=""){
                    $result[] = search_waypoint($row['FIX_IDENT'],$row['FIX_ICAO_CODE'],$row['FIX_SECT_CODE']);
                }
                $lastwp = $row;
        }
        if(substr($row['WAYPOINT_DESCR_CODE'],3,1)=="M"){
            $gapoint = search_waypoint($row['FIX_IDENT'],$row['FIX_ICAO_CODE'],$row['FIX_SECT_CODE']);
            $gapoint['IDENT'] = "G/A";
            $result[] = $gapoint;
        }
    }
    return $result;
}
$list  = [];
if($type=='departure'){
    $runwaycon = [];
    $sql = "select * from RUNWAY WHERE ARPT_IDENT='$airport' AND RUNWAY_IDENT='$runway'";
    $ret = $conn->query($sql);
    $row = $ret->fetchArray(SQLITE3_ASSOC);
    $runwaycon = ['IDENT'=>$runway,'LAT'=>$row['RUNWAY_LAT'],'LON'=>$row['RUNWAY_LON']];
    //var_dump($sql);
    if($transition!=''){
        $legs = [];
        $sql = "select * from AIRPORT_PROCEDURE WHERE ARPT_IDENT='$airport' AND PROC_IDENT='$proc_ident' and ROUTE_TYPE=5";
        $ret = $conn->query($sql);
        while($row = $ret->fetchArray(SQLITE3_ASSOC)){
            $legs[] = $row;
        }
        $sql = "select * from AIRPORT_PROCEDURE WHERE ARPT_IDENT='$airport' AND PROC_IDENT='$proc_ident' and (ROUTE_TYPE=6 OR ROUTE_TYPE=3) AND TRANSITION_IDENT='$transition'";
        $ret = $conn->query($sql);
        while($row = $ret->fetchArray(SQLITE3_ASSOC)){
            $legs[] = $row;
        }
        $list = build_route($legs,$runwaycon);
        if($legs==[]){
            exit("err");
        }
    }else{
        $legs = [];
        $sql = "select * from AIRPORT_PROCEDURE WHERE ARPT_IDENT='$airport' AND PROC_IDENT='$proc_ident' and (ROUTE_TYPE=5 OR ROUTE_TYPE=2)";
        $ret = $conn->query($sql);
        while($row = $ret->fetchArray(SQLITE3_ASSOC)){
            $legs[] = $row;
        }
        //var_dump($runwaycon);
        $list = build_route($legs,$runwaycon);
        if($legs==[]){
            exit("err");
        }
    }
}else if($type=="arrive"){
    $legs = [];
    if($proc_ident!=""){
        if($transition==''){
            $sql = "select * from AIRPORT_PROCEDURE WHERE ARPT_IDENT='$airport' AND PROC_IDENT='$proc_ident' and (ROUTE_TYPE=5 OR ROUTE_TYPE=2) and (TRANSITION_IDENT='$runway' or TRANSITION_IDENT='')";
            $ret = $conn->query($sql);
            while($row = $ret->fetchArray(SQLITE3_ASSOC)){
                $legs[] = $row;
            }
            $sql = "select * from AIRPORT_PROCEDURE WHERE ARPT_IDENT='$airport' AND PROC_IDENT='$proc_ident' and (ROUTE_TYPE=6 OR ROUTE_TYPE=3)";
            $ret = $conn->query($sql);
            while($row = $ret->fetchArray(SQLITE3_ASSOC)){
                if($row['TRANSITION_IDENT']==$runway||(strpos($row['TRANSITION_IDENT'],'B')>0&&substr($row['TRANSITION_IDENT'],0,4)==substr($runway,0,4))){
                    $legs[] = $row;
                }
            }
        }
    }
    $list = build_route($legs);
}else if($type=="approach"){
    $legs = [];
    $sql = "select * from AIRPORT_PROCEDURE WHERE ARPT_IDENT='$airport' AND PROC_IDENT='$proc_ident' and SUBS_CODE='F' and ROUTE_TYPE='A' and TRANSITION_IDENT='$transition'";
    $ret = $conn->query($sql);
    while($row = $ret->fetchArray(SQLITE3_ASSOC)){
        $legs[] = $row;
    }
    $sql = "select * from AIRPORT_PROCEDURE WHERE ARPT_IDENT='$airport' AND PROC_IDENT='$proc_ident' and SUBS_CODE='F' and ROUTE_TYPE<>'A'";
    $ret = $conn->query($sql);
    while($row = $ret->fetchArray(SQLITE3_ASSOC)){
        $legs[] = $row;
    }
    $list = build_route($legs);
}
$retu['code']=200;
$retu['msg']="";
$retu['content'] = $list;
echo json_encode($retu);
?>