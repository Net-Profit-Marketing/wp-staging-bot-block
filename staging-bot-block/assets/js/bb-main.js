function bb_redirect_settings_mainform_submit_validation() {
	var bb_redirect_enabled = document.getElementById("bb_redirect_enabled").value;
	var bb_redirect_url = document.getElementById("bb_redirect_url").value;
	var bb_redirect_choice_get="";
	var ele = document.getElementsByName('bb_redirect_choice'); 
    for(i = 0; i < ele.length; i++) { 
        if(ele[i].checked) 
        bb_redirect_choice_get=ele[i].value; 
    }
    if(bb_redirect_choice_get=="Redirect Bots" || bb_redirect_choice_get=="Redirect Bots & Users")
    {
    	if(bb_redirect_url=="")
    	{
    		document.getElementById("bb_redirect_url_error").innerHTML="Please enter valid URL.";
			return false;
    	}
    	if(bb_redirect_enabled=="1" || bb_redirect_enabled==1)
		{
			var bb_redirect_urlres = bb_redirect_url.match(/(http(s)?:\/\/.)?(www\.)?[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&//=]*)/g);
		 	var bb_redirect_urlres_pattern = /(http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/;
			
			if(bb_redirect_urlres_pattern.test(bb_redirect_url) && bb_redirect_urlres !== null)
			{
				console.log(bb_redirect_url);
				return true;
			}
			else
			{
				document.getElementById("bb_redirect_url_error").innerHTML="Please enter valid URL.";
				return false;
			}
		}
    }
	
}
