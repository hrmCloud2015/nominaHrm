<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Novedades extends TableGateway
{
    private $idTmatz;
    private $idEmp;
    private $idConc;
    private $valor;
    private $idCal;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_novedades', $adapter, $databaseSchema,$selectResultPrototype);
    }    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($idMat, $idTmat, $idEmp, $idCon, $valor, $idCal ,$tipo ,$valhor, $fecha, $idProy, $cuotas)
    {
       $dev=0;
       $ded=0;
       $hor=0;
       if ( $valhor == 2 )
       {
         if ($tipo==1)
             $dev = $valor;
         else
             $ded = $valor;           
       }else{
         $hor = $valor;  
       }
       $datos=array
       (
           'idMatz'    => $idMat,
           'idTmatz'   => $idTmat,
           'idEmp'     => $idEmp,
           'idConc'    => $idCon,
           'idCal'     => $idCal,
           'devengado' => $dev,
           'deducido'  => $ded,
           'horas'     => $hor,
           'fechaEje'  => $fecha,           
           'idProy'    => $idProy,                      
           'porCuotas'    => $cuotas,                                 
        );
        
        $this->insert($datos);
        $inserted_id = $this->lastInsertValue;  
        return $inserted_id;              

    }
    
    public function getRegistroId($id)
    {
       $id  = (int) $id;
       $rowset = $this->select(array('id' => $id));
       $row = $rowset->current();
      
       if (!$row) {
          throw new \Exception("No hay registros asociados al valor $id");
       }
       return $row;
     }        
     public function delRegistro($id)
     {
       $this->delete(array('id' => $id));               
     }
}
?>
