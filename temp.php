<?php
    $page = $_SERVER['PHP_SELF'];
    $sec = "60";
    $oursel= $_POST['Seltime'];

    if ($oursel == "") {
       $oursel = "Hourly";
    };
    ?>
    
<?php
    include "libchart/classes/libchart.php";
    $chartT = new LineChart($width=800, $height=300);
    $chartRH = new LineChart($width=800, $height=300);
    $tSet = new XYDataSet();
    $rhSet = new XYDataSet();

    $val;    
    $num_rows;
    $last_rh;
    $last_t;
    $label_skip = 1;
    $max_count = 240; /* 4 hours for Hourly */

    $conn = mysql_connect("localhost", "root", "root");
    if ($conn == NULL) {
        die ("mysql connect error\n");
    }

    mysql_select_db("am2301db", $conn)
        or die ("cannot select am2301db database\n"); 

    $result = mysql_query("select count(1) FROM am2301db");
    $row = mysql_fetch_array($result);
    $num_rows = $row[0];

    if ($oursel == "Hourly") {
       $label_skip = 10;
       $max_count = 60 * 4;
    }
    else if ($oursel == "Daily") {
       $label_skip = 60;
       $max_count = 60 * 24;
    }
    else {
       $label_skip = 60 * 6;
       $max_count = 60 * 24 * 7;
    }
    $row_start = 0;

    if($num_rows >= $max_count) {
        $row_start = $num_rows - $max_count;
    }
    $row_size = $num_rows - $row_start;

    $rowset_handle = mysql_query("SELECT * FROM am2301db LIMIT $row_start, $row_size", $conn);
    if ($rowset_handle == NULL) {
       die ("no rowset\n");
    }
    $i = 0;

# try to find the best way to show the values in the graph.

    while (1) {
    	$i++;
        $row  = mysql_fetch_row($rowset_handle);
        if (!$row) {
	   break;
	}	
	$last_t  = $t  = $row[1] / 10.0;
	$last_rh = $rh = $row[2] / 10.0;
	$timeval = strtotime($row[0]);
	$minute = date('i', $timeval);
	$hour = date('G', $timeval);

	if (($hour * 60 + $minute) % $label_skip == 0) {
	   $time = date('G:i', $timeval);

        }
	else {
	   $time = "";
        }
	$tSet->addPoint(new Point($time, $t));
	$rhSet->addPoint(new Point($time, $rh));
    }	

    $chartT->setDataSet($tSet);
    $chartT->setTitle("Temperature");
    $chartT->render("generated/t_$oursel.png");
    $chartRH->setDataSet($rhSet);
    $chartRH->setTitle("Relative Humidity");
    $chartRH->render("generated/rh_$oursel.png");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Temperature (T) and Humidity (RH)</title>
    <meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-15" />
    <style>
        body { font-family: serif; }
        p { font-family: arial; font-size: 16px; }
        h2 { font-size: 24px; 
           font-style: arial;
        }
        ul { font-weight: bolder; }
        </style>

</head>
<body>

<table border = 1> 
    <tr>
    <td valign=top>
        <table border = 1>
	<h2>
        <tr> <td>
	    <p> <?php echo "Records: $num_rows" ?> </p>
	</td> </tr>
	<tr> <td>
            <p> <?php echo "Current T: <b> $last_t&deg;C </b>" ?> </p>
        </td> </tr>
	<tr> <td>
	   <p> <?php echo "Current RH: <b> $last_rh % <b>" ?> </p>
	</td></tr>
	<tr><td>
 
<form name "mysubmit" id="mysubmit" action="<?php echo $_SERVER["PHP_SELF"]; ?>"method="post">
<select name="Seltime" onchange="this.form.submit();">
<option <?php if ($oursel == "Hourly") {echo "selected";} ?>value="Hourly"> Hourly </option>
<option <?php if ($oursel == "Daily") {echo "selected";} ?> value="Daily">  Daily </option>
<option <?php if ($oursel == "Weekly") {echo "selected";} ?> value="Weekly">  Weekly </option>
</select>
</form>
<script>setTimeout(function(){document.getElementById('mysubmit').submit()}, 60*1000);</script>	
	</td></tr>
	</h2>
        </table>
    </td>
    
    <td>
    <table>
        <tr> </td>
      	    <img alt="Line chart" src="generated/t_<?php echo $oursel ?>.png" style="border: 1px solid gray;"/>
        </td> </tr>
        <tr> <td>
	    <img alt="Line chart" src="generated/rh_<?php echo $oursel ?>.png" style="border: 1px solid gray;"/>
        </td> </tr>
    </table>
    </td>
</table>

</body>
</html>
