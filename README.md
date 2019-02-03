![](https://jazmy.com/wp-content/uploads/github_header.jpg)
# SkinnyPi - Weight Loss Party
A [Laravel](https://laravel.com), [Raspberry 
Pi](https://www.raspberrypi.org/) & [FitBit](https://www.fitbit.com) 
Project for Weight Loss Party Mode

View my website: [https://jazmy.com](https://jazmy.com)

**[Skinny Pi in Action - Video Demo](https://youtu.be/MJ7T2EUNAlY)** 

[Demo Laravel Site](https://skinnypi.jazmy.com)

I thought it would be fun to setup a 
Raspberry Pi so that every time I lost weight, it would flash lights 
and play music. I referred to this as “Party mode” or “Happy Dance.” It 
would be positive reinforcement and a fun way to start my morning. I 
had just completed an “Internet of Things” MIT course in Grad School so 
I had a basic understanding of communication protocols.

## Requirements
1.  A Fitbit Compatible Scale 
Any scale that allows you to sync your weight wirelessly to the Fitbit site. 
I am using the [Fitbit Aria](https://amzn.to/2WDCBHl)

2.  A Raspberry Pi (with wifi)
I am using the [Raspberry Pi 3 Model B](https://amzn.to/2DP3pg7)

3.  Hosted Cloud Server 
I am using [Cloudways](https://www.cloudways.com/en/?id=45081) which is easy for Laravel

4.  Hosted MQTT Server 
I am using the free version of [CloudMQTT](https://www.cloudmqtt.com/)

## How it Works *(simplified version)*:
1.  You step on the Fitbit Aria Scale and it syncs over wifi to the 
fitbit server
    
2.  The Fitbit API triggers that a new weight was added and does a call 
to your cloud server
    
3.  Your cloud server stores the new weight and calculates to see if it 
was higher or lower than the previous weight
    
4.  If the weight is lower it sends a message to your MQTT service
    
5.  Your Raspberry Pi is subscribed to the MQTT service so it receives 
the message and triggers a python script.
    
6.  The python script plays music and flashes lights.


![SkinnyPi Diagram](https://jazmy.com/wp-content/uploads/skinnypi_diagram.jpg)

# Part 1 - The Raspberry Pi
**Requirements**
 - [Raspberry Pi 3 Model B](https://amzn.to/2DP3pg7)
 - [32 GB Micro SD Card](https://amzn.to/2G7xjig)
 - [Pimoroni Blinkt Leds](https://amzn.to/2t3lRLR)
 - [Speakers 8 ohm 0.25W](https://amzn.to/2DeTMWT)
 - [Clear Case for Pi 3](https://amzn.to/2DPi3Em)
## 1.1 Assembling Your Pi
I'll assume if you are reading this that you know how to put together a 
raspberry pi.
## 1.2 Installing Software
### 1.2.1 Installing Raspbian from your PC:
1.  You will need to install Raspbian on your Raspberry Pi:
    [https://www.raspberrypi.org/downloads/](https://www.raspberrypi.org/downloads/)
    
2.  To get the Raspbian image on your Raspberry Pi you need a tool 
called win32 disk imager: 
[https://sourceforge.net/projects/win32diskimager/](https://sourceforge.net/projects/win32diskimager/)
    
3.  Unzip the raspbian software which is just an image file. It may 
takes a couple minutes because it’s 2 GB.
    
4.  Open Win32 disk and browse for the raspbian image.
    
5.  Select the “Device” which is the drive your SD card is on. When 
selecting the device, make sure you select the correct letter because 
it will delete everything on that drive. It’s a common issue for people 
to accidentally delete a usb backup drive they may have also had 
connected.
    
6.  Then click the “Write” button. It takes a few minutes to write 
depending on the speed of your card.
    
7.  Once done, windows may get confused because it can no longer 
recognize the drive and it will ask you to format your sd card. Ignore 
that error. Your SD card is complete, now you plop it in your raspberry 
pi
### 1.2.2 Setting up your Pi the First Time
 1. Turn on your Raspberry Pi
 2. Login with default credentials: Username: pi and Password: 
raspberry
 3. It will ask you to change those
 4. It will do updates
 5. It will ask you to connect to your wifi
 6. Open the terminal window
 7. Type “ifconfig” to figure out your IP address *(Another option is 
to type: sudo /etc/rc.local )*
 9. Click on the Raspberry icon for a drop down menu and select 
“Preferences” then “Raspberry Pi Configurations”
 10. Enable SSH
### 1.2.3 Install Mosquitto
There are several applications that can be used to send and receive 
through MQTT, but the simplest on the Raspberry Pi is probably 
Mosquitto. We will install this on the Raspberry Pi first: 
```bash
sudo 
apt-get install -y mosquitto mosquitto-clients 
```
### 1.2.4 Install Paho MQTT
In order to allow your Pi to subscribe to your MQTT channel and listen 
for messages, you need to install Paho-mqtt. 
Tutorial: 
[https://tutorials-raspberrypi.com/raspberry-pi-mqtt-broker-client-wireless-communication/](https://tutorials-raspberrypi.com/raspberry-pi-mqtt-broker-client-wireless-communication/)
> Install both python 2 and 3 versions. If you use Thonny to test you 
> coe it will look for python3 version. If you run your code in the 
> terminal then it will default to python2.  From my experience, life 
> is easier when you install both.
```bash 
sudo pip install paho-mqtt 
sudo pip3 install paho-mqtt 
```
### 1.2.5 Install Blinkt Scripts
Blinkt are eight super-bright RGB LED lights that you can add to your 
raspberry pi and control from python scripts. Each pixel on Blinkt! is 
individually controllable and dimmable allowing you to create 
gradients, pulsing effects, or just flash them on and off like crazy. 

Blinkt Python Scripts for SkinnyPi:
[https://github.com/jazmy/raspberrypi-skinnypi](https://github.com/jazmy/raspberrypi-skinnypi)

Blinkt Python Code Examples:
[https://github.com/pimoroni/blinkt/tree/master/examples](https://github.com/pimoroni/blinkt/tree/master/examples) 

Create a folder “skinnypi” 
/home/pi/skinnypi/skinnypi.py 

Place your audio files in wav format in that folder.
It's best to name your audio files 1.wav, 2.wav, 3.wav, etc...

> Make sure your audio is set to “analog” If you have your raspberry pi 
> connected to a monitor via HDMI then it will default to playing the 
> audio on HDMI. To make that change, right click on the audio icon in 
> the upper right hand corner of the raspberry pi OS.

Execute the file 
```bash 
sudo python skinnypi/skinnypi.py 
```
### 1.2.6 Setup your Blinkt Scripts to Automatically Run on Boot
[https://learn.sparkfun.com/tutorials/how-to-run-a-raspberry-pi-program-on-startup#method-1-rclocal](https://learn.sparkfun.com/tutorials/how-to-run-a-raspberry-pi-program-on-startup#method-1-rclocal) 

```bash 
sudo nano /etc/rc.local 
``` 

This is tricky but you need to 
ensure that you wait 10 seconds to give your pi enough time to boot and 
connect to the network before you run your script. This will create a 
log file if there are problems. 

```bash 
sudo bash -c '(sleep 10;/usr/bin/python3 /home/pi/skinnypi/skinnypi.py > /home/pi/skinnypi/skinnypi.log 2>&1)' & 
```

# Part 2 - The Cloud Server
If you are looking for a managed web host, with easy laravel site 
creation, then I highly recommend [Cloudways](https://www.cloudways.com/en/?id=45081). Cloudways will setup a 
laravel site and mysql database in minutes.
## 2.1 Laravel App

![SkinnyPi Laravel App Screenshot](https://jazmy.com/wp-content/uploads/skinnypi_laravel_screenshot.jpg)

### Requirements
- Laravel 5.7
    
- MySQL
    
- Laravel Authentication - php artisan make:auth
### Dependencies Included with Package
This project includes Fitbit Provider for OAuth 2.0 Client 
[https://github.com/djchen/oauth2-fitbit](https://github.com/djchen/oauth2-fitbit)
 
### Installation Instructions
This repository is an entire Laravel site and not just a package. It 
takes a couple steps to install but I will try to make it as simple as 
possible. 

```bash 
composer update 
``` 

*Note: The package will 
automatically register itself using [Laravel's](https://laravel.com) 
package discovery feature for versions 5.6 and above. This means you do 
not need to update your config/app.php file.*

#### Step Three:
We need to add the additional database tables so run the following 
command

```
bash php artisan migrate 
```

## 2.2 Setup a Fitbit App (client id & client secret)
Fitbit API Documentation: [https://dev.fitbit.com/](https://dev.fitbit.com/) 
Creating a Laravel fitbit application is 
really tricky but here are the basic steps for how it works: 
- The user authorizes the application 
- Fitbit returns the user back to the callback url with the authorization token
    
Example Callback URL: 
[https://your.domain.com/authorize](https://your.domain.com/authorize) 
When you setup the fitbit application you need to give it a callback 
URL that it will send the token. We MUST store that authorization token 
so that we will be able to make subsequent requests on behalf of the 
user later.
  
- We use the id of the authorization token that was stored to create a 
subscription for the user on our subscriber endpoint. That means every time a change happens, fitbit will fire your 
callback page.
    
Example endpoint subscriber URL: 
[https://your.domain.com](https://your.domain.com/authorize)[/callback](http://phplaravel-36874-256428.cloudwaysapps.com/callback)
  
- Once the subscription is complete, the user sees a message on the 
screen saying that their subscription has been created or that the 
subscription already exists if it already does.
      
- When the user's weight data comes into Fitbit, fitbit will notify our 
application by calling the subscriber url. 
[https://your.domain.com](https://your.domain.com/authorize)[/callback](http://phplaravel-36874-256428.cloudwaysapps.com/callback)
      
- Fitbit only NOTIFIES us that the user weight data has changed. It 
does not send us the actual weight.
      
- In the getCallback method in the FitBitController, we have to quickly 
save the notification data that was sent to us and return a 204 
response to fitbit so that fitbit will know that we received the 
notification.
      
- There is no way the application can receive the notification data in 
the callback, fetch the notification details from fitbit, parse the 
notification, fetch the affected user, save the new weight log, do the 
weight comparison check, send the requested mqtt message and reply 
fitbit with the required 'HTTP 204 No Content' status all within 3 
seconds in the callback. So you need to break it down into a couple 
steps to prevent fitbit from banning your api access.
      
- The solution is to setup a Laravel queue listener so that it is 
always running. When fitbit triggers the callback you add a job to the 
queue. The queue listener should process the notification within 5 
seconds or so of receiving the push from the callback.
      
- 'Supervisor' is a process monitor program that will monitor the queue 
listener process and automatically restart it in case the process 
crashes.
      
- The job you create will do the following:
    
- fetch the notification details from fitbit
    
- parse the notification
    
- fetch the affected user
    
- save the new weight log
    
- do the weight comparison check
    
- send the requested mqtt message
      
- Then we will run the 
[http://phplaravel-36874-256428.cloudwaysapps.com/notification-details](http://phplaravel-36874-256428.cloudwaysapps.com/notification-details) 
route to go through the notifications we have saved and create a 
request to fitbit to fetch the details of each of those notifications. 
It is in the response that we get from fitbit that we will have the 
weight that has changed.
      
- The weight data will then be written to a file inside the 
storage/app/weight_logs folder.

# Part 3 - MQTT Server
MQTT is a way to push and subscribe to messages and works well on 
Internet of Things (iOT) devices like a Raspberry pi. You setup the Pi 
to subscribe to a MQTT channel so whenever your server pushes a message 
to that MQTT channel then MQTT will automatically push it to any device 
subscribed. MQTT Introduction: 
[http://www.steves-internet-guide.com/mqtt/](http://www.steves-internet-guide.com/mqtt/)
  
If the user has lost weight it should send this json to MQTT: 
```json 
$message = { "color": "1", "style": "1", "seconds": "10", "audio": "1"} 
``` 
If the user has not lost weight it should send this json to MQTT: 
```json
$message = { "color": "2", "style": "2", "seconds": "10", "audio": "2"}
``` 
