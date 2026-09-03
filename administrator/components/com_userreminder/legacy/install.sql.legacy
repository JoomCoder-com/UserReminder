CREATE TABLE IF NOT EXISTS `#__userreminder` (
`userid` int(11) NOT NULL,  
`datesent` datetime NOT NULL,  
`remindernumber` int(11) DEFAULT 0,  
`type` int(11),
`optoutcode` varchar(255),  
UNIQUE KEY `userid` (`userid`)) 
ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `#__userreminder_log` (
`id` bigint(20) NOT NULL AUTO_INCREMENT,  
`userId` int(11) NOT NULL,  
`username` varchar(240) NOT NULL,  
`description` text NOT NULL,  
`date` datetime NOT NULL,  
PRIMARY KEY (`id`)) 
ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `#__userreminder_sch` (
`id` int(11) NOT NULL auto_increment,  
`daysent` tinyint(2) unsigned default '0',  
`monthsent` tinyint(2) unsigned default '0',  
`yearsent` int(4) default '0',  
`timesent` int(11) default '0',
PRIMARY KEY  (`id`)) 
ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `#__userreminder_optout` (
`user_id` int(11) default '0',  
PRIMARY KEY  (`user_id`)) 
ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `#__userreminder_optout_usergroups` (
`group_id` int(11) default '0',  
PRIMARY KEY  (`group_id`)) 
ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;