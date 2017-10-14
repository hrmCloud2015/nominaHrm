<?php
/*
 * STANDAR DE NISSI MODELO A LA BD MAESTROS
 * 
 */
namespace Nomina\Model\Entity;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Adapter\Adapter;

class Tipcalen extends TableGateway
{
    private $id;
    private $nombre;
    private $valor;
    private $fecha;
    private $tipo;
    private $inemp;
        
    public function __construct(Adapter $adapter = null, $databaseSchema = null, ResultSet $selectResultPrototype = null)
    {
        return parent::__construct('n_tip_calendario', $adapter, $databaseSchema,$selectResultPrototype);
    }

    private function cargaAtributos($datos=array())
    {
        $this->id      = $datos["id"];    
        $this->nombre  = $datos["nombre"];   
        $this->valor   = $datos["numero1"];  
        $this->fecha   = $datos["fecDoc"];  
        $this->tipo    = $datos["tipo"];  
        $this->inemp   = $datos["check1"];  
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
           'nombre'  => $this->nombre,
           'valor'   => $this->valor,
           'fecha'   => $this->fecha,
           'tipo'    => $this->tipo,
           'fecIemp' => $this->inemp,
        );
       if ($id==0) // Nuevo registro
          $this->insert($datos);
       else // Mdificar registro
          $this->update($datos, array('id' => $id));
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
