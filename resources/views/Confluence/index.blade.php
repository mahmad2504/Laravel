<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Program Calendar</title>
    <!-- Styles -->
	<link href="apps/confluence/css/style.css" rel="stylesheet"/>
	<link href="{{ asset('libs/calendar/calendar.css') }}" rel="stylesheet"/>
	<style>
		
		.lightgrey
		{
			background-color:F5F5F5;
		}
		.red 
		{
		  background-color:red;
		  color:white !important;
		}
		.orange 
		{
		  background-color:orange;
		  
		}
		.green 
		{
		  background-color:lightgreen;
		  
		}
		.blue 
		{
		  background-color:lightblue;
		  
		}
		.year_header_ccol 
		{
		  position: sticky;
		  position: -webkit-sticky;
		  background-color:#FFFFE0 !important;
	      border: 1px solid black;
          left: 0px;
		}
		.month_header_ccol 
		{
			position: sticky;
		    position: -webkit-sticky;
		    background-color:#FFFFE0 !important;
	        border: 1px solid black;
            left: 0px;
		}
		.sprint_header_ccol 
		{
			position: sticky;
		    position: -webkit-sticky;
		    background-color:#FFFFE0 !important;
	        border: 1px solid black;
            left: 0px;
		}
		.week_header_ccol 
		{
			position: sticky;
		    position: -webkit-sticky;
		    background-color:#FFFFE0 !important;
	        border: 1px solid black;
            left: 0px;
		}
		#day_header_ccol_0 
		{
			position: sticky;
		    position: -webkit-sticky;
		    background-color:#FFFFE0 !important;
	        border: 1px solid black;
            left: 0px;
		}
		#day_header_ccol_1 
		{
			position: sticky;
		    position: -webkit-sticky;
		    background-color:#FFFFE0 !important;
	        border: 1px solid black;
            left: 72px;
		}
		#day_header_ccol_2 
		{
			position: sticky;
		    position: -webkit-sticky;
		    background-color:#FFFFE0 !important;
	        border: 1px solid black;
            left: 183px;
		}
		.day_header_scol 
		{
			background-color:#8FBC8F 
		}
		.CalendarTable
		{
			background-color:#F5F5F5;
		}
		.week_row_header
		{
			font-size:9px !important;
		}
		
		.ccol
		{
			padding-left:5px !important;
			padding-right:5px !important;
			text-align: left !important;
			background-color:#F0F8FF;
		}
		.jiralink
		{
			font-size:9px !important;
		}
		
	</style>
</head>
<body>
	<div style="margin:auto; width:90%;">
		<h2> EPT Program </h2> 
		<div style="margin:auto; width:100%;overflow-x: scroll;">
			<div id="calendar"></div>
		</div>
		<img width=200px src="{{ asset('apps/confluence/images/legend.png') }}"></img>
	</div>
	<script src="http://code.jquery.com/jquery-1.7.2.min.js"></script>
	<script src="{{ asset('libs/calendar/calendar.js') }}" ></script>
	<script>
	var data=@json($data);
	var tabledata = @json($tabledata);
	var ms;
	$(function() {
		"use strict";
		var calendar = new Calendar(tabledata);
		calendar.Show("calendar");
		$('#day_header_ccol_0').html('Product');
		$('#day_header_ccol_1').html('Release');
		$('#day_header_ccol_2').html('');
		
		var row = calendar.GenerateWeekRow('rs',1);
		calendar.AddRow(row);
		$('#week_rs_ccol_0').html('<span>.</span>');
		for(var i=0;i<data.length;i++)
		{
			var d = data[i];
			var row = calendar.GenerateWeekRow('r'+i,1);
			calendar.AddRow(row);
			if(d.category !== undefined)
				$('#week_r'+i+'_ccol_0').html('<span>'+d.category+'</span>');
			if(d.name !== undefined)
				$('#week_r'+i+'_ccol_1').html('<span>'+d.name+'</span>');
			if(d.desc !== undefined)
				$('#week_r'+i+'_ccol_2').html('<span>'+d.desc+'</span>');
			
			//$('#week_r'+i+'_scol_'+d.duedate).html('<span>'+d.label+'</span>');
			for(var j=0;j<d.values.length;j++)
			{
				var v=d.values[j];
				//console.log('#week_r'+i+'_scol_'+v.duedate);
				//
				//console.log($('#week_r'+i+'_scol_'+v.duedate).html
				$('#week_r'+i+'_scol_'+v.duedate).html('<span>'+v.label+'</span>');
				$('#week_r'+i+'_scol_'+v.duedate).addClass(v.class);
				//ms['#week_r'+i+'_scol_'+v.duedate][]= v.label;
			}
		}
		var row = calendar.GenerateWeekRow('rl',1);
		calendar.AddRow(row);
		$('#week_rl_ccol_0').html('<span>.</span>');
		//var row = calendar.GenerateWeekRow('r1',1);
		//calendar.AddRow(row);
		//$('#week_r1_ccol_0').html('<span>Nucleus</span>');
		//$('#week_r1_ccol_1').html('<span style="margin-left:5px;margin-right:5px">Risc-V 2010</span>');
		//$('#week_r1_ccol_2').html('<span style="margin-left:5px;margin-right:5px">Baseline</span>');
		//$('#r1_2021_41').html('<span style="color:black;background-color:lightgreen;">FF</span>');
	});
    </script>

</body>
</html>