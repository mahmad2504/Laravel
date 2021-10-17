function Calendar(tabledata,custom_columns=3)
{
	var self = this;
	this.tabledata=tabledata;
	this.today_color='#90EE90';
	this.future_cell_color='#FFFFE0';//'#F0F8FF';
	this.col = [1,1,1];
	this.custom_columns = custom_columns;
	this.Show = function(tag)
	{
		self.CreateTable(tag);
	}
	this.AddRow = function(row)
	{
		this.table.append(row);
	}
	this.CreateTable = function(tag)
	{
		var table = $('<table>');
		table.addClass("CalendarTable");
		$('#'+tag).append(table);
		var row=1;
		yearrow = self.GenerateYearRow('header');
		table.append(yearrow);
		
		monthrow = self.GenerateMonthRow('header');
		table.append(monthrow);
		
		sprintrow = self.GenerateSprintRow('header');
		table.append(sprintrow);
		
		weekrow = self.GenerateWeekRow('header');
		table.append(weekrow);
		
		dayrow = self.GenerateDayRow('header');
		dayrow.attr("height","15px");
		dayrow.attr("width","15px");
		table.append(dayrow);
		this.table = table;
	
	}
	this.AppendRow= function(row)
	{
		this.table.append(row);
	}
	this.MonthName = function(month)
	{
		if(month == 1)
			return "Jan";
		else if(month == 2)
			return "Feb";
		else if(month == 3)
			return "Mar";
		else if(month == 4)
			return "Apr";
		else if(month == 5)
			return "May";
		else if(month == 6)
			return "Jun";
		else if(month == 7)
			return "Jul";
		else if(month == 8)
			return "Aug";
		else if(month == 9)
			return "Sep";
		else if(month == 10)
			return "Oct";
		else if(month == 11)
			return "Nov";
		else if(month == 12)
			return "Dec";
	
		return month;
	}
	this.GenerateYearRow = function(rtag,empty=0)
	{
		var yearArray = this.tabledata.years;
		var prefix = 'year_'+rtag;
		var c=1;
		var row = $('<tr>');
		row.attr('id',prefix);
		
		if(rtag == 'header')
		{
			col = $('<th colspan="'+this.custom_columns+'">');
			col.addClass(prefix+'_ccol');
			col.attr('id',prefix+'_ccol_'+0);
			col.html('Year');
			row.append(col);
		}
		else
		{
			for(var i=0;i<this.custom_columns;i++)
			{
				var col = $('<th>');
				col.addClass(prefix+'_ccol');
				col.attr('id',prefix+'_ccol_'+i);
				row.append(col);
			}
		}
		var color='#DCDCDC';
		for (var year in yearArray) 
		{
			var today = yearArray[year].includes(1);

			if(today)
				color=this.today_color;
			else
			{
				if(color==this.today_color)
					color=this.future_cell_color;
			}
			colspan = Object.keys(yearArray[year]).length;
			if(colspan < 15)
				year = '';
			col = $('<th colspan="'+colspan+'">');
			
			col.addClass(prefix+'_scol');
			col.attr('id',prefix+'_scol_'+year);
			if(empty==0)
			{
				col.html(year);
				col.css('background-color',color);
			}
			row.append(col);
		}
		return row;
	}
	this.GenerateMonthRow =  function(rtag,empty=0)
	{
		var prefix = 'month_'+rtag;
		monthArray = this.tabledata.months;
		//console.log(monthArray);
		var row = $('<tr>');
		row.attr('id',prefix);
		
		if(rtag == 'header')
		{
			col = $('<th colspan="'+this.custom_columns+'">');
			col.addClass(prefix+'_ccol');
			col.attr('id',prefix+'_ccol_'+0);
			col.html('Month');
			row.append(col);
		}
		else
		{
			for(var i=0;i<this.custom_columns;i++)
			{
				var col = $('<th>');
				col.addClass(prefix+'_ccol');
				col.attr('id',prefix+'_ccol_'+i);
				row.append(col);
			}
		}
		var color='#DCDCDC';
		console.log(monthArray);
		for (var month in monthArray) 
		{
			var mindex = month;
			var today = monthArray[month].includes(1);
			
			if(today)
				color=this.today_color;
			else
			{
				if(color==this.today_color)
					color=this.future_cell_color;
			}
			colspan = Object.keys(monthArray[month]).length;
			if(colspan <= 15)
				month = '';
			
			col = $('<th colspan="'+colspan+'">');
			
			col.addClass(prefix+'_scol');
			col.attr('id',prefix+'_scol_'+mindex);
			if(empty ==0)
			{
				col.html(self.MonthName(month.substring(5)));
				col.css('background-color',color);
			}
			row.append(col);
		}
		return row;
	}
	this.GenerateWeekRow =  function(rtag,empty=0)
	{
		var prefix = 'week_'+rtag;
		weekArray = this.tabledata.weeks;
		var c=1;
		var row = $('<tr>');
		row.attr('id',prefix);
		if(rtag == 'header')
		{
			col = $('<th colspan="'+this.custom_columns+'">');
			col.addClass(prefix+'_ccol');
			col.attr('id',prefix+'_ccol_'+0);
			col.html('Week');
			row.append(col);
		}
		else
		{
			for(var i=0;i<this.custom_columns;i++)
			{
				var col = $('<th>');
				col.addClass(prefix+'_ccol');
				col.addClass('ccol');
				col.attr('id',prefix+'_ccol_'+i);
				row.append(col);
			}
		}
		var color='#DCDCDC';
		for (var week in weekArray) 
		{
			var today = weekArray[week].includes(1);
			if(today)
				color=this.today_color;
			else
			{
				if(color==this.today_color)
					color=this.future_cell_color;
			}
			colspan = Object.keys(weekArray[week]).length;
			col = $('<th colspan="'+colspan+'">');
			
			year = week.substring(0,4);
			weeknum = week.substring(5);
			
			col.addClass(prefix+'_scol');
			col.attr('id',prefix+'_scol_'+year+'_'+weeknum);
			
			if(empty==0)
			{
				col.html(weeknum);
				col.css('background-color',color);
			}
			row.append(col);
		}
		return row;
	}
	this.GenerateSprintRow =  function(rtag,empty=0)
	{
		var prefix = 'sprint_'+rtag;
		
		sprintArray = this.tabledata.sprints;
		var row = $('<tr>');
		
		if(rtag == 'header')
		{
			col = $('<th colspan="'+this.custom_columns+'">');
			col.addClass(prefix+'_ccol');
			col.attr('id',prefix+'_ccol_'+0);
			col.html('Sprint');
			row.append(col);
		}
		else
		{
			for(var i=0;i<this.custom_columns;i++)
			{
				var col = $('<th>');
				col.addClass(prefix+'_ccol');
				col.attr('id',prefix+'_ccol_'+i);
				row.append(col);
			}
		}
		
		var color='#DCDCDC';
		for (var sprint in sprintArray) 
		{
			sprintdata = sprintArray[sprint];
			//console.log(sprintdata);
			start = sprintdata[0].date;
			start=new Date(start).toString().slice(4, 10);
			end = sprintdata[sprintdata.length-1].date;
			end=new Date(end).toString().slice(4, 10);
			
			var today = sprintdata.find(function(obj, index) 
			{
				if(obj.today == 1)
					return true;
			});
			
		
			if(today)
				color=this.today_color;
			else
			{
				if(color==this.today_color)
					color=this.future_cell_color;
			}
			colspan = Object.keys(sprintArray[sprint]).length;
			col = $('<th colspan="'+colspan+'">');
			
			col.addClass(prefix+'_scol');
			col.attr('id',prefix+'_scol_'+sprint);
							
			var html=sprint+"<br><span style='color:green;font-size:8px;'>"+start+"-"+end+"</span>";
			col.attr('title',start+"-"+end);
			
			if(empty==0)
			{
				if(colspan < 21)
					html = '';
				col.html(html);
				col.css('background-color',color);
			}
			row.append(col);
		}
		return row;
	}
	this.GenerateDayRow =  function(rtag,empty=0)
	{
		var prefix = 'day_'+rtag;
		
		var var1='';
		var var2='';
		dayArray =  this.tabledata.days;
		var row = $('<tr>');
		for(var i=0;i<this.custom_columns;i++)
		{
			var col = $('<th>');
			col.addClass(prefix+'_ccol');
			col.attr('id',prefix+'_ccol_'+i);
			row.append(col);
		}	
		var color='#8FBC8F';
		var todate =  new Date();
		todate = todate.toDateString();
		for (var day in dayArray) 
		{
			var date =  new Date(day);
			
			var today = (todate === date.toDateString());
			
			if(today)
				color=this.today_color;
			else
			{
				if(color==this.today_color)
					color=this.future_cell_color;
			}
			col = $('<td>');
			col.addClass(prefix+'_scol');
			col.attr('id',prefix+'_scol_'+day);	
			if(empty == 0)
			{
				col.attr('title',date.toDateString());
				col.css('background-color',color);
			}
			//col.html('<span style="font-size:5px;color:red;">'+day.substring(8,10)+'</span>');
			row.append(col);
		}
		return row;
	}
}