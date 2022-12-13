<?php 

    // header('Access-Control-Allow-Origin: *');
    // header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
    // header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    // header("Allow: GET, POST, OPTIONS, PUT, DELETE");

    class Api extends Rest {
        
        public $dbConn;

		public function __construct(){

			parent::__construct();

			$db = new Db();
			$this->dbConn = $db->connect();

        }

        public function test(){
        }

        public function metas(){

            $day = intval(date('j')) + 1;
            $month = date('n');
            $year = date('Y');

            // Monto Actual
            $query = "  SELECT NVL(ROUND(a.monto_anio_actual/1000000,2),0) AS monto_actual,
                                ROUND(b.monto_anio_anterior/1000000,2) AS monto_anterior
                        FROM ( SELECT SUM( NVL(impuesto,0)   +
                                            NVL(multas,0)     +
                                            NVL(convenios,0)) AS monto_anio_actual             
                                    FROM tbl_actual_pagos
                                    WHERE TO_NUMBER(TO_CHAR(fecha,'DD'  )) <= $day
                                    AND TO_NUMBER(TO_CHAR(fecha,'MM'  ))  = $month
                                    AND TO_NUMBER(TO_CHAR(fecha,'YYYY'))  = $year) a,
                                ( SELECT SUM( NVL(impuesto,0)   +
                                            NVL(multas,0)     +
                                            NVL(convenios,0)) AS monto_anio_anterior
                                    FROM tbl_historia_pagos
                                    WHERE TO_NUMBER(TO_CHAR(fecha,'DD'  )) <= $day
                                    AND TO_NUMBER(TO_CHAR(fecha,'MM'  ))  = $month
                                    AND TO_NUMBER(TO_CHAR(fecha,'YYYY'))  = $year) b";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $monto = oci_fetch_array($stid, OCI_ASSOC);                        

            // Proyeccion
            $query = "  SELECT ROUND(sum(to_number(proyeccion_solvente))/1000000,2) AS proyeccion_solvente,
                        ROUND(sum(to_number(proyeccion_mora))/1000000,2) AS proyeccion_mora                     
                        FROM tbl_proyeccion_pagos
                        WHERE TO_NUMBER(TO_CHAR(fecha,'MM'  ))  = $month 
                        AND TO_NUMBER(TO_CHAR(fecha,'YYYY')) = $year";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $proyeccion = oci_fetch_array($stid, OCI_ASSOC);

            // Real
            $query = "  SELECT sum(to_number(impuesto))/1000000 AS impuesto,
                                sum(to_number(multas))/1000000 AS multas,
                                sum(to_number(convenios))/1000000 AS convenios
                        FROM tbl_actual_pagos
                            WHERE TO_NUMBER(TO_CHAR(fecha,'DD'  )) <= $day 
                            AND TO_NUMBER(TO_CHAR(fecha,'MM'  ))  = $month
                            AND TO_NUMBER(TO_CHAR(fecha,'YYYY'))  = $year";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $real = oci_fetch_array($stid, OCI_ASSOC);

            $impuesto_mora  = doubleval($real['IMPUESTO']);	
			$multas_mora    = doubleval($real['MULTAS']);
            $convenios_mora = doubleval($real['CONVENIOS']);	
            $monto_actual = doubleval($monto["MONTO_ACTUAL"]);

            $impuesto  = round($impuesto_mora,2);	
			$multas    = round($multas_mora,2);
            $convenios = round($convenios_mora,2);
            
            // Calculos
            $iusi_mora     = round((($multas_mora * 100 / 20) + $convenios_mora + $multas_mora),2);
            $iusi_solvente = round(($monto_actual - $iusi_mora),2);

            $data = array($proyeccion, $real);

            // Grafica Mora
            $mora_categories = array("Solventes", "Morosas");

            $mora_series = array(
                array(
                    "name" => "Real",
                    "data" => array($iusi_solvente, $iusi_mora)
                ),
                array(
                    "name" => "Meta",
                    "data" => array(doubleval($proyeccion["PROYECCION_SOLVENTE"]), doubleval($proyeccion["PROYECCION_MORA"]))
                ),
            );

            $grafica_mora = array();
            $grafica_mora["CATEGORIES"] = $mora_categories;
            $grafica_mora["SERIES"] = $mora_series;
            $grafica_mora["META_MES"] = doubleval($proyeccion["PROYECCION_SOLVENTE"]) + doubleval($proyeccion["PROYECCION_MORA"]);

            // $this->returnResponse(SUCCESS_RESPONSE, $grafica_mora);

            echo json_encode($grafica_mora);

        }

        public function moras(){

             // Mora mes actual 
            $query = "ALTER SESSION SET nls_date_format = 'dd/mm/yyyy'";
            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $query = " SELECT sum(to_number(impuesto))/1000000 AS impuesto,
                        sum(to_number(multas))/1000000 AS multas,
                        sum(to_number(convenios))/1000000 AS convenios
                        FROM tbl_actual_pagos
                        WHERE TO_DATE(fecha, 'DD/MM/YYYY') BETWEEN TO_DATE('01/08/2019', 'DD/MM/YYYY')
                        AND TO_DATE('22/08/2019', 'DD/MM/YYYY')";

            $stid = oci_parse($this->dbConn, $query);
            oci_execute($stid);

            $mora_mes_actual = oci_fetch_array($stid, OCI_ASSOC);

            $this->returnResponse(SUCCESS_RESPONSE, $mora_mes_actual);

        }

    }
    

?>