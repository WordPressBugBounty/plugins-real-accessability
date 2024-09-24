<?php
/**
 * Plugin Name: Real Accessability
 * Plugin URI: https://realmedia.co.il
 * Description: Real Accessability plugin adds custom accessability such as font resizer, color inverse, black & white view and much more
 * Version: 1.0
 * Author: REALMEDIA
 * Author URI: https://realmedia.co.il
 * License: GPL2
 * Text Domain: real-accessability
 * Domain Path: /languages
 */

// Load Localization 
function my_plugin_load_plugin_textdomain() {
    load_plugin_textdomain( 'real-accessability', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'my_plugin_load_plugin_textdomain' ); 

// Load all the needed scripts 
function real_accessability_assets() {	
	wp_enqueue_style( 'real-accessability', plugin_dir_url( __FILE__ ) . 'real-accessability.css', array(), '1.0' );
	wp_enqueue_script( 'real-accessability', plugin_dir_url( __FILE__ ) . 'real-accessability.js', array('jquery'), '1.0', true );
}
add_action( 'wp_enqueue_scripts', 'real_accessability_assets' );


// Add accessability class to body
function real_accessability_body_class($classes) {
	
		$cookie = $_COOKIE['real-accessability'];
	
        $classes[] = 'real-accessability-body';
        
		if(isset($cookie)) {
			$cookie = str_replace("\\", "", $cookie);
			$cookie = json_decode($cookie);
			
			if($cookie->effect !== null) {
				$classes[] = $cookie->effect;
			}
			
			if($cookie->linkHighlight !== false) {
				$classes[] = 'real-accessability-linkHighlight';
			}
			
			
			if($cookie->regularFont !== false) {
				$classes[] = 'real-accessability-regularFont';
			}
		}
		        
        return $classes;
}
add_filter('body_class', 'real_accessability_body_class');


// Toolbar HTML
function real_accessability_html() {
    
    $imgPlayBlue  = plugins_url('images/play-blue.gif',__FILE__);
    $imgPlayGray  = plugins_url('images/play-gray.gif',__FILE__);
    $imgPauseBlue = plugins_url('images/pause-blue.gif',__FILE__);
    $imgPauseGray = plugins_url('images/pause-gray.gif',__FILE__);
    $imgStopBlue  = plugins_url('images/stop-blue.gif',__FILE__);
    $imgStopGray  = plugins_url('images/stop-gray.gif',__FILE__);
    $imgUserBlue  = plugins_url('images/user-blue.gif',__FILE__);
    
    
	echo '
	<div id="real-accessability">
		<a href="#" id="real-accessability-btn"><i class="real-accessability-loading"></i><i class="real-accessability-icon"></i></a>
		<ul>
			<li><a href="#" id="real-accessability-biggerFont">'. __( 'Increase Font', 'real-accessability') .'</a></li>
			<li><a href="#" id="real-accessability-smallerFont">'. __( 'Decrease Font', 'real-accessability') .'</a></li>
			<li><a href="#" id="real-accessability-grayscale" class="real-accessability-effect">'. __( 'Black & White', 'real-accessability') .'</a></li>
			<li><a href="#" id="real-accessability-invert" class="real-accessability-effect">'. __( 'Inverse Colors', 'real-accessability') .'</a></li>
			<li><a href="#" id="real-accessability-linkHighlight">'. __( 'Highlight Links', 'real-accessability') .'</a></li>
			<li><a href="#" id="real-accessability-regularFont">'. __( 'Regular Font', 'real-accessability') .'</a></li>
			<li><a href="#" id="real-accessability-reset">'. __( 'Reset', 'real-accessability') .'</a></li>

		</ul>
        
        <div id="real-accessability-player">
        
            <span>Page Reader</span>
        
            <img alt="Press Enter to Read Page Content Out Loud" src="'.$imgPlayBlue.'" id="btnAccPlay" onclick="accPlayer(\'play\')" onkeypress="if (event.keyCode==13){ accPlayer(\'play\'); }" style="cursor:pointer">

            <img alt="Press Enter to Pause or Restart Reading Page Content Out Loud" src="'.$imgPauseGray.'" id="btnAccPause" onclick="accPlayer(\'pause\')" onkeypress="if (event.keyCode==13){ accPlayer(\'pause\'); }" style="cursor:pointer">

            <img alt="Press Enter to Stop Reading Page Content Out Loud" src="'.$imgStopGray.'" id="btnAccStop" onclick="accPlayer(\'stop\')" onkeypress="if (event.keyCode==13){ accPlayer(\'stop\'); }" style="cursor:pointer">

            <a href="'.plugins_url( 'support.php', __FILE__ ).'" target="_blank"><img src="'.$imgUserBlue.'" id="btnAccSupport" border="0" onClick="location.href=\''.plugins_url( 'support.php', __FILE__ ).'\';" alt="Screen Reader Support"></a>            
            
        </div>    
            
        
		<div id="real-accessability-copyright"><a href="#">Real Accessability</a></div>
	</div>
	<!-- Init Real Accessability Plugin -->
	<script type="text/javascript">
		jQuery( document ).ready(function() {
			jQuery.RealAccessability({
				hideOnScroll: false
			});
		});	
	<!-- /END -->
	</script>
    <script src="//code.responsivevoice.org/responsivevoice.js"></script>
    <script type="text/javascript">

	function determineEnglish() {
		var body = document.body;
		var textContent = body.textContent || body.innerText;
		var textContent = textContent.replace(/\n/g," ");
		var textContent = textContent.replace(/\r/g," ");
		var textContent = textContent.replace(/\t/g," ");
		var textContent = textContent.replace(/ /g,"");
		var textLeft = textContent.replace(/\W+/g,"");
		var oldc = textContent.length;
		var newc = textLeft.length;
		var ratio = newc/oldc;
		if(ratio>.8) {
			return "english";
		} else {
			return "other";
		}
	}



    window.accPlayerStatus = "uninit";

    if(responsiveVoice.voiceSupport() && determineEnglish()=="english") {
        var obj = document.getElementById("btnAccPlay");
        obj.style.cursor="pointer";  
    } else {
        document.getElementById("real-accessability-player").style.display="none";
    }

    if(navigator.userAgent.indexOf("OPR")!=-1) {
        document.getElementById("real-accessability-player").style.display="none";
    } 
    
    function accPlayer(btnType) {

        // TURN ALL TO GRAY

        var playObj  = document.getElementById("btnAccPlay");
        var pauseObj = document.getElementById("btnAccPause");
        var stopObj  = document.getElementById("btnAccStop");

        if(btnType=="play") {

            if(window.accPlayerStatus=="uninit") {

                // CHANGE STATUS TO PLAYING
                window.accPlayerStatus = "playing";

                // LOAD THE PAGE CONTENT ALONE
                var u = location.href;
                var s = document.createElement("script");
                s.setAttribute("type","text/javascript")
                s.src = "//508fi.org/js/speech.php?u="+encodeURIComponent(u);
                document.getElementsByTagName("head")[0].appendChild(s);

                // ASSIGN CORRECT COLORS
                playObj.src  = playObj.src.replace("blue","gray");
                stopObj.src  = stopObj.src.replace("gray","red");
                pauseObj.src = pauseObj.src.replace("gray","blue");

            } else if(window.accPlayerStatus=="playing") {

            } else if(window.accPlayerStatus=="paused") {

                // CHANGE STATUS TO PLAYING
                window.accPlayerStatus = "playing";

                // RESUME PLAYING
                responsiveVoice.resume();

                // ASSIGN CORRECT COLORS
                 playObj.src  = playObj.src.replace("blue","gray");
                 stopObj.src  = stopObj.src.replace("gray","red");
                 pauseObj.src = pauseObj.src.replace("gray","blue");

            } else if(window.accPlayerStatus=="stopped") {

                // CHANGE STATUS TO PLAYING
                window.accPlayerStatus = "playing";

                // LOAD THE PAGE CONTENT ALONE
                var u = location.href;
                var s = document.createElement("script");
                s.setAttribute("type","text/javascript")
                s.src = "//508fi.org/js/speech.php?u="+encodeURIComponent(u);
                document.getElementsByTagName("head")[0].appendChild(s);

                // ASSIGN CORRECT COLORS
                 playObj.src  = playObj.src.replace("blue","gray");
                 stopObj.src  = stopObj.src.replace("gray","red");
                 pauseObj.src = pauseObj.src.replace("gray","blue");

            } else {

            }

        } else if(btnType=="pause") {
            if(window.accPlayerStatus=="uninit") {

            } else if(window.accPlayerStatus=="playing") {

                // CHANGE STATUS TO PLAYING
                window.accPlayerStatus = "paused";

                // PAUSE READING
                responsiveVoice.pause();

                // ASSIGN CORRECT COLORS
                 playObj.src  = playObj.src.replace("gray","blue");
                 stopObj.src  = stopObj.src.replace("gray","red");
                 pauseObj.src = pauseObj.src.replace("blue","gray");

            } else if(window.accPlayerStatus=="paused") {

            } else if(window.accPlayerStatus=="stopped") {

            } else {

            }

        } else if(btnType=="stop") {

            if(window.accPlayerStatus=="uninit") {

            } else if(window.accPlayerStatus=="playing") {

                // STOP READING
                responsiveVoice.cancel();

                // ASSIGN CORRECT COLORS
                 playObj.src  = playObj.src.replace("gray","blue"); 
                 stopObj.src  = stopObj.src.replace("red","gray");
                 pauseObj.src = pauseObj.src.replace("blue","gray");

            } else if(window.accPlayerStatus=="paused") {

                // STOP READING
                responsiveVoice.cancel();

                // ASSIGN CORRECT COLORS
                 playObj.src  = playObj.src.replace("gray","blue"); 
                 stopObj.src  = stopObj.src.replace("red","gray");
                 pauseObj.src = pauseObj.src.replace("blue","gray");

            } else if(window.accPlayerStatus=="stopped") {

            } else {}
            
        } else {}

    }


    </script>         
    
    
    
    
    
    
    
    
    
    
    
    
    
    
	';
    
    
    
}
add_action( 'wp_footer', 'real_accessability_html' );
 
 ?>
