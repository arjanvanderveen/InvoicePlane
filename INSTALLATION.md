# Installation Guide

This fork of InvoicePlane is meant to run inside a Docker/Podman container. The examples below use Podman, but if you want to use Docker, just replace podman to docker accordingly.
To build the image:

```bash
podman build . -t invoiceplane --format docker
```

To create a MariaDB database:
```sql
CREATE DATABASE <database name> CHARACTER SET utf8 COLLATE utf8_unicode_ci;
CREATE USER '<database user>'@'%' IDENTIFIED BY '<database user password>';
GRANT ALL PRIVILEGES ON <database name>.* TO '<database user>'@'%';
```

To run the image:

```bash
podman volume create invoiceplane_uploads

podman run --name invoiceplane \
	-v invoiceplane_uploads:/var/www/html/uploads \
	-e DISABLE_READ_ONLY=true \
	-e ENABLE_INVOICE_DELETION=true \
	-e IP_URL=http://localhost:8052/ \
	-e MYSQL_DB=<database name> \
	-e MYSQL_HOST=<database host> \
	-e MYSQL_PORT=3306 \
	-e MYSQL_USER=<database user> \
	-e MYSQL_PASSWORD=<database user password> \
	-e DISABLE_SETUP=false \
	-e SETUP_COMPLETED=false \
	-p 8052:80 \
	--replace -d localhost/invoiceplane

```
Now go to ```http://localhost:8052/index.php/setup and follow the wizard. Afterwards, restart the container as follows:

```bash
podman run --name invoiceplane_dev \
	-v invoiceplane_dev_uploads:/var/www/html/uploads \
	-e DISABLE_READ_ONLY=true \
	-e ENABLE_INVOICE_DELETION=true \
	-e IP_URL=http://localhost:8052/ \
	-e MYSQL_DB=<database name> \
	-e MYSQL_HOST=<database host> \
	-e MYSQL_PORT=3306 \
	-e MYSQL_USER=<database user> \
	-e MYSQL_PASSWORD=<database user password> \
	-e DISABLE_SETUP=true \
	-e SETUP_COMPLETED=true \
	-p 8052:80 \
	--replace -d localhost/invoiceplane
```

Good luck with further configuring the InvoicePlane instance.

## OpenID login
If you want to be able to login into InvoicePlane without having to keep record of different username and passwords for each InvoicePlane instance, you can configure to use an OpenID identity provider like keycloak. For this work, create a client in your identity provider environment, and add the following environment variables to start your InvoicePlane container:
(this example uses Keycloak)
```bash
        -e OPENID_PROVIDER_URL=https://<hostname>/realms/<realm name> \
        -e OPENID_CLIENT_ID=<client id configured in Keycloak> \
        -e OPENID_CLIENT_SECRET=<client secret configured in Keycloak> \
```

When configured, the login page of InvoicePlane will display a new button 'OpenID' which you can click to login at the identity provider. If a user that does not exist yet in InvoicePlane is logged into InvoicePlane for the first time, the username and email address are used from the identity provider, and all remaining fields of first InvoicePlane user are copied to the new user. This is a dirty work-around to prevent that completely different company name and address are getting displayed on invoices created by the new user. InvoicePlane is not really meant to handle multiple users imho.

