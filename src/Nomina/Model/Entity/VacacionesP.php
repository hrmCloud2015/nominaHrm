<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class VacacionesP extends TableGateway
{
    private $id;
    private $idVac;
    private $fechaI;
    private $fechaF; 
    private $dias;

    
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_vacaciones_p', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id      = $datos["id"];    
        $this->idVac   = $datos["idVacs"];   
        $this->fechaI  = $datos["fecsal"];  
        $this->fechaF  = $datos["fecReg"];  
        $this->dias    = $datos["dias"];  

    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($idPvac, $dias, $idVac, $numPerConv, $diasDin)
    {
      // $this->delRegistro($idVac); 
       
       $datos=array
       (
           'idVac'    => $idVac,           
           'idPvac'   => $idPvac,
           'dias'     => $dias, // Dias de disfrute 
           'diasDin'  => $diasDin, // Dias en dinero            
           'perGrup'  => $numPerConv, // Especial para Cajamag, numero de periodos dentro la convecion de un empleado
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
       $this->delete(array('idVac' => $id));               
     }    
     
}
?>
