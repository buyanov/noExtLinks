#!/usr/bin/env bash
VERSION=`git describe`
sed -e 's/$Id$/${VERSION}/g' noextlinks.php