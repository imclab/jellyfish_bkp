<html><head><meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
<title>WebGL</title>
<link href="css/style.css" rel="stylesheet" type="text/css">
<script type="text/javascript" src="js/lib/jquery-1.4.2.min.js"></script>
<script type="text/javascript" src="js/lib/matrix4x4.js"></script>

<script type="text/javascript" src="js/initWin.js"></script>
<script type="text/javascript" src="js/initUniforms.js"></script>
<script type="text/javascript" src="js/initTextures.js"></script>
<script type="text/javascript" src="js/initShaders.js"></script>
<script type="text/javascript" src="js/initBuffers.js"></script>

<script type="text/javascript" src="js/tick.js"></script>
<script type="text/javascript" src="js/drawScene.js"></script>

<script id="jelly-shader-vs" type="x-shader/x-vertex">
attribute vec3 aVertexPosition;
attribute vec3 aVertexNormal;
attribute vec3 aVertexColor;
attribute vec3 aTextureCoord;

uniform mat4 uWorld;
uniform mat4 uView;
uniform mat4 uViewInv;
uniform mat4 uWorldView;
uniform mat4 uWorldViewProj;
uniform mat4 uWorldInvTranspose;

uniform float uNear;
uniform float uFar;
uniform vec3 uLightPos;
uniform float uLightRadius;
uniform vec4 uLightCol;
uniform vec4 uLightSpecCol;
uniform float uSpecPower;
uniform vec4 uAmbientCol;
uniform vec4 uFresnelCol;
uniform float uFresnelPower;
  
uniform float uCurrentTime;

varying vec2 vTextureCoord;
varying vec3 vVertexNormal;
varying vec3 vWorldEyeVec;
varying vec4 vWorld;
varying vec4 vWorldView;
varying vec4 vWorldViewProj;
varying vec4 vWorldInvTranspose;
varying vec4 vViewInv;

varying float vDepth;
varying vec3 vSpecular;
varying vec3 vDiffuse;
varying vec3 vAmbient;
varying vec3 vFresnel;
  
void main(void) {
  
  //Vertex Animation
  float speed = uCurrentTime/15.;
  float offset = smoothstep(0.0,1.,max(0.,-aVertexPosition.y-0.8)/10.);
  vec3 pos = aVertexPosition
  + (vec3(aVertexColor.x,aVertexColor.y,aVertexColor.z)/12.0
  *sin(speed*15.0 + aVertexPosition.y/2.0) * (1.-offset));
  pos = pos 
  + (vec3(aVertexColor.x,aVertexColor.y,aVertexColor.z)/8.0
  *sin(speed*30.0 + aVertexPosition.y/.5) * (1.-offset));
  gl_Position = uWorldViewProj * vec4(pos, 1.0); 
  
  //matrices
  vWorld =               uWorld * vec4(pos, 1.0);
  vWorldView =           uWorldView * vec4(pos, 1.0);
  vWorldViewProj =       uWorldViewProj * vec4(pos, 1.0);
  vWorldInvTranspose =   uWorldInvTranspose * vec4(pos, 1.0);
  vViewInv =             uViewInv * vec4(pos, 1.0);

  //vertex data
  vec4 worldViewPos = uWorldViewProj * vec4(pos, 1.0); 
  vVertexNormal = normalize((uWorldInvTranspose * vec4(aVertexNormal, 1.)).xyz);
  
  vec3 worldPos = (uWorld * vec4(pos, 1.0)).xyz;
  vWorldEyeVec = normalize(worldPos - uViewInv[3].xyz); 
  
  //diffuse
  vec3 lightDir = normalize(uLightPos - vWorld.xyz);
  float diffuseProduct = max(dot(normalize(vVertexNormal.xyz), lightDir), 0.0);
  float lightFalloff = pow(max(1.0-(distance(uLightPos, vWorld.xyz)/uLightRadius), 0.0),2.0);
  vDiffuse = uLightCol.rgb * vec3(diffuseProduct * lightFalloff * uLightCol.a);

  //ambient (top)
  vAmbient = uAmbientCol.rgb * vec3(uAmbientCol.a) * vVertexNormal.y;

  //fresnel
  float fresnelProduct = pow(1.0-max(abs(dot(vVertexNormal, -vWorldEyeVec)), 0.0), uFresnelPower);
  vFresnel = uFresnelCol.rgb * vec3(uFresnelCol.a * fresnelProduct);

  //depth
  vDepth = (vWorldViewProj.z+uNear)/uFar;
  
  vTextureCoord = vec2(aTextureCoord[0], aTextureCoord[1]);
}
</script>

<script id="jelly-shader-fs" type="x-shader/x-fragment">
#ifdef GL_ES
precision highp float;
#endif
  
uniform sampler2D uSampler;
uniform sampler2D uSampler1;

uniform float uShaderDebug;
uniform float uCurrentTime;
  
varying vec2 vTextureCoord;
varying vec3 vVertexNormal;
varying vec3 vWorldEyeVec;
varying vec4 vWorld;
varying vec4 vWorldView;
varying vec4 vWorldViewProj;
varying vec4 vWorldInvTranspose;
varying vec4 vViewInv;

varying float vDepth;
varying vec3 vDiffuse;
varying vec3 vSpecular;
varying vec3 vAmbient;
varying vec3 vFresnel;
varying vec3 vFog;

void main(void) {
  vec3 caustics = texture2D(uSampler1, vec2((vWorld.x)/24.+uCurrentTime/20., (vWorld.z-vWorld.y)/48.+uCurrentTime/40.)).rgb;
  vec4 colorMap = texture2D(uSampler, vec2(vTextureCoord.s, vTextureCoord.t));
  float transparency = colorMap.a+pow(vFresnel.r,2.)-0.3;
  
  vec4 composit = vec4(((vAmbient + vDiffuse + caustics)*colorMap.rgb), transparency);
  
  vec4 finalColor = composit;//composit

  gl_FragColor = finalColor;
}
</script>

<script type="text/javascript">
$(document).ready(function(){
		webGLStart();
	});
</script>

</head>
<body>
	<div id="gradient-bg"></div>
	<canvas id="webgl-canvas"></canvas>
    
    <div id="console">
        <div class="console-field" style="width:90px;">Time: <span id="current-time">NaN</span>  Frame rate: <span id="frameRate">NaN</span></div>
        <div class="console-field" style="display: none;">     	
            <b>camera</b>
            near <input type="text" class="textboxDebug" id="near" value="1">
            far <input type="text" class="textboxDebug" id="far" value="1000">
            fov <input type="text" class="textboxDebug" id="fov" value="30">
        </div>     
       <div class="console-field" style="display: none;">
        	<b>shader debug</b>     	
            <input type="radio" name="shaderDebug" value="0" checked > composit <br/>
            <input type="radio" name="shaderDebug" value="1"> diffuse <br/>
            <input type="radio" name="shaderDebug" value="2"> ambient<br/>
            <input type="radio" name="shaderDebug" value="3"> caustics <br/>
            <input type="radio" name="shaderDebug" value="4"> color <br/>
        	  <input type="radio" name="shaderDebug" value="5"> specular <br/>
            <input type="radio" name="shaderDebug" value="6"> fresnel <br/>
            <input type="radio" name="shaderDebug" value="7"> normal <br/>
            <input type="radio" name="shaderDebug" value="8"> alpha <br/>
            <input type="radio" name="shaderDebug" value="9"> zDepth <br/>
        </div>
       <div class="console-field" style="display: none;">
        	<b>model debug</b>     	
            <input type="radio" name="modelDebug" value="0"> LOD0 <br/>
            <input type="radio" name="modelDebug" value="1" checked> LOD1 <br/>
            <!-- Too much bandwidth. I have to turn this off -->
            <!--<input type="radio" name="modelDebug" value="2"> LOD2 <br/>-->
            <!--<input type="radio" name="modelDebug" value="3"> LOD3 <br/>-->
        </div>
        <div class="console-field">
        	<b>
          		Created by <a href="http://aleksandarrodic.com">Aleksandar Rodic</a><br />
							using <a href="http://en.wikipedia.org/wiki/WebGL">WebGL</a><br />
              Special thanks to <a href="http://learningwebgl.com/blog/">Giles Thomas</a>
          </b>
				</div>
        <div class="console-field" style="visibility: hidden;">  
        	<b>Directional light</b>
          	<span class="input-section">position</span>
            <input type="text" class="textboxDebug" id="lightX" value="10" />
            <input type="text" class="textboxDebug" id="lightY" value="40" />
            <input type="text" class="textboxDebug" id="lightZ" value="-60" /><br />
            <span class="input-section">color</span>
            <input type="text" class="textboxDebug"id="lightR" value="0.8" />
            <input type="text" class="textboxDebug"id="lightG" value="1.3" />
            <input type="text" class="textboxDebug" id="lightB" value="1.1" />
            <input type="text" class="textboxDebug" id="lightA" value="1" /><br />
            <span class="input-section">spec color</span>
            <input type="text" class="textboxDebug"id="lightSpecR" value="1" />
            <input type="text" class="textboxDebug"id="lightSpecG" value="1" />
            <input type="text" class="textboxDebug" id="lightSpecB" value="1" />
            <input type="text" class="textboxDebug" id="lightSpecA" value="0.2" /><br />
            <span class="input-section">radius</span>
            <input type="text" class="textboxDebug"id="lightRadius" value="200" /><br />
            <span class="input-section">spec power</span>
            <input type="text" class="textboxDebug"id="lightSpecPower" value="1" /><br />
				</div>
        <div class="console-field" style="visibility: hidden;">
        	<b>Ambient light</b>
          <span class="input-section">color</span>
        	<input type="text"  class="textboxDebug" id="ambientR" value="0.3" />
          <input type="text"  class="textboxDebug" id="ambientG" value="0.2" />
          <input type="text"  class="textboxDebug" id="ambientB" value="1" />
          <input type="text"  class="textboxDebug" id="ambientA" value="1" />
				</div>
        <div class="console-field" style="visibility: hidden;">
        	<b>fog</b>
          <span class="input-section">color</span>
        	<input type="text"  class="textboxDebug" id="fogR" value="0.3" />
          <input type="text"  class="textboxDebug" id="fogG" value="0.5" />
          <input type="text"  class="textboxDebug" id="fogB" value="0.8" />
          <input type="text"  class="textboxDebug" id="fogA" value="0.2" />
				</div>
        <div class="console-field" style="visibility: hidden;">
        	<b>fresnel</b>
          <span class="input-section">color</span>
        	<input type="text"  class="textboxDebug" id="fresnelR" value="0.8" />
          <input type="text"  class="textboxDebug" id="fresnelG" value="0.7" />
          <input type="text"  class="textboxDebug" id="fresnelB" value="0.6" />
					<input type="text"  class="textboxDebug" id="fresnelA" value="1.1" /><br/>
          <span class="input-section">power</span>
          <input type="text"  class="textboxDebug" id="fresnelPower" value="1" />
				</div>
        
    </div>
<script src="http://www.google-analytics.com/urchin.js" type="text/javascript">
</script>
<script type="text/javascript">
try {
_uacct = "UA-258449-15";
urchinTracker();
} catch(err) {}
</script>
</body>
</html>