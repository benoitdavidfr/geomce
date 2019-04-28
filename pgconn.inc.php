<?php
// connexion Postgres non secrète
if (1 && ($_SERVER['HTTP_HOST']=='localhost'))
  return "host=172.17.0.4 dbname=postgres user=postgres password=benoit";
else
  return "host=postgresql-bdavid.alwaysdata.net dbname=bdavid_geomce user=bdavid_geomce password=geomce";
