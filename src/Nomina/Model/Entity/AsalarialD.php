<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;
use Principal\Model\LogFunc; // Traer datos de session activa y datos del pc 


class AsalarialD extends TableGateway
{
    private $id;
    private $estado;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_asalarial_d', $adapter, $databaseSchema,$selectResultPrototype);
    }
        
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array(), $idAsal, $idEsal ,$sal,$por,$salA)
    {
       // Datos de transaccion
       $t = new LogFunc($this->adapter);
       $dt = $t->getDatLog();              
               
       //$datos=array
       //(
         //  'idAsal'      => $idAsal,
        //   'idEsal'      => $idEsal,
         //  'salarioAct'  => str_replace( array(",",".") , "",$sal), 
          // 'porInc'      => $por,
          // 'salarioNue'  => str_replace( array(",",".") , "",$salA),           
        //);

       $datos=array
       (
           'idAsal'      => $idAsal,
           'idEsal'      => $idEsal,
           'salarioAct'  => $sal, 
           'porInc'      => $por,
           'salarioNue'  => $salA ,           
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
