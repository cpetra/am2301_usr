/**************************************************************
 Name        : aw2301_usr.c
 Version     : 0.1

 Copyright (C) 2013 Constantin Petra

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU Lesser General Public
License as published by the Free Software Foundation; either
version 2.1 of the License, or (at your option) any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
Lesser General Public License for more details.
***************************************************************************/

#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <stdint.h>
#include <unistd.h>
#include <time.h>
#include <signal.h>
#include <syslog.h>
#include <sys/sysinfo.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <mysql/mysql.h>
#include <fcntl.h>

static void do_init(void);

typedef struct __sensor_data {
    float rh;
    float t;
} sensor_data;

static MYSQL *con;

static void quit_handler(int sig)
{
    signal(sig, SIG_IGN);
    mysql_close(con);
    exit(0);
}

static int mysql_stuff_init(int drop)
{
    con = mysql_init(NULL);

    if (con == NULL) {
	fprintf(stderr, "%s\n", mysql_error(con));
	return -1;
    }

    if(mysql_real_connect(con, "localhost", "root", "root",
			  "am2301db", 0, NULL, 0) == NULL)
    {
	fprintf(stderr, "%s\n", mysql_error(con));
	mysql_close(con);
	return -1;
    }
    if (drop != 0) {
	if (mysql_query(con, "CREATE DATABASE am2301db") != 0) {
	    fprintf(stderr, "%s\n", mysql_error(con));
	}
	if (mysql_query(con, "DROP TABLE IF EXISTS am2301db") != 0) {
	    fprintf(stderr, "%s\n", mysql_error(con));
	    mysql_close(con);
	    return -1;
	}
	if (mysql_query(con, "CREATE TABLE am2301db(ts TIMESTAMP, RH INT, Temp INT)") != 0)     {
	    fprintf(stderr, "%s\n", mysql_error(con));
	    mysql_close(con);
	    return -1;
	}
    }
    return 0;
}

static int mysql_add(sensor_data *s)
{
    char query[256];
    char st[128];

    time_t t = time(NULL);
    struct tm tm = *localtime(&t);
    strftime(st, 128, "%F %T", &tm);
    sprintf(query, "INSERT INTO am2301db VALUES(\"%s\", %d, %d)",
	    st, (int)(s->t * 10.0), (int)(s->rh * 10.0));
    if (mysql_query(con, query) != 0) {
	fprintf(stderr, "%s\n", mysql_error(con));
	return -1;
    }
    return 0;
}

static void do_init(void)
{
    signal (SIGTERM, quit_handler);
    signal (SIGHUP, quit_handler);

    if (mysql_stuff_init(0) != 0) {
	exit(1);
    }
}

static int read_am2301(sensor_data *s, int mode)
{
    FILE *fp;
    int ret;
    fp = fopen("/proc/am2301", "r");
    if (!fp) {
	return -1;
    }
    ret = fscanf(fp, "T : %f\n", &s->t);
    ret += fscanf(fp, "RH : %f\n", &s->rh);
    fclose(fp);
    return (ret == 2) ? 0 : -1;
}

int main(int argc, char *argv[])
{
    int ret;
    int add_db = 1;
    sensor_data s;
    int i;
    

    if (argc >= 2) {
	if (strcmp(argv[1], "reset") == 0) {
	    mysql_stuff_init(1);
	    if (con != 0) {
		mysql_close(con);
	    }
	    return 0;
	}
	if (strcmp(argv[1], "nodb") == 0) {
	    add_db = 0;
	}
    } 

    do_init();

    /* Try to read one value, if that doesn't work, try 10 more times,
     * then bail out.
     */
    for (i = 0; i < 10; i++) {
	ret = read_am2301(&s, 0);
	if (ret == 0) {
	    break;
	}
	sleep(2000);
    }
    if (ret == 0) {
	printf("t = %.1f, rh = %.1f\n", s.t, s.rh);
	/* Drop the first measurement */
	if (add_db != 0) {
	    mysql_add(&s);
	}

    }
    return 0;
}
