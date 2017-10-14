<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Tippresta extends TableGateway
{
    private $id;
    private $nombre;
    private $idConS;
    private $idConI;
    private $idTnom;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_tip_prestamo', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id     = $datos["id"];    
        $this->nombre = $datos["nombre"];   
        $this->idConS  = $datos["idConc"]; 
        $this->idConI  = $datos["tipo"]; 
        $this->idTnom  = $datos["idTnom"]; 
    }
    
    public function getRegistro()
    {
       $datos = $this->select();
       return $datos->toArray();
    }
    
    public function actRegistro($data=array())
    {
       self::cargaAtributos($data);
       $id = $this->id;
       $datos=array
       (
           'nombre'=>$this->nombre,
           'idConS' =>$this->idConS,
           'idConE' =>$this->idConI,
           'idTnom' =>$this->idTnom,
           'tipo' =>$data['tipo3'],
        );
       if ($id==0) // Nuevo registro
       {
          $this->insert($datos);
          $inserted_id = $this->lastInsertValue;  
          return $inserted_id;                     
       }
       else // Mdificar registro
       {
          $this->update($datos, array('id' => $id));
          return 0;           
       }
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
