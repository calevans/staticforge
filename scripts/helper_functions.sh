#!/bin/bash

#
# Functions needed to color the text
#
green_text () {
  tput setaf 2; echo $1;tput sgr0
}
yellow_text () {
  tput setaf 3; echo $1;tput sgr0
}
red_text () {
  tput setaf 1; echo $1;tput sgr0
}
white_text () {
  tput setaf 7; echo $1;tput sgr0
}
