<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Gnominac extends TableGateway
{
    private $id;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_tip_calendario_d', $adapter, $databaseSchema,$selectResultPrototype);
    }
       
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }

    public function getRegistroId($tipo,$idGrupo,$idCal)
    {      
       $datos=array
       (
           'idGrupo' => $idGrupo,
           'idCal'   => $idCal,    
           'idTnom'  => $tipo,
           'idTnom'  => $tipo,
        );       
       $rowset = $this->select($datos, array("estado"=>0) );
       $row = $rowset->current();
       return $row;
     }        
     
    public function actRegistro( $tipo,$idGrupo,$idCal,$fechai,$fechaf, $estado ,$n )
    {
       $datos=array
       (
           'estado'  => $estado,
        );
       if ($n==0) // Nuevo registro
          $this->insert($datos);
       else // Mdificar registro
          $this->update($datos, array(  'idGrupo' => $idGrupo,
                                        'idCal'   => $idCal,    
                                        'idTnom'  => $tipo,
                                        'fechai'  => $fechai,
                                        'fechaf'  => $fechaf
                                         ) );
    }    

}
?>
