<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Lux Perfumes - Comece sua revenda</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      background-color: #0e0e0e;
      color: #f8f8f8;
      font-family: 'Segoe UI', sans-serif;
    }

    .bg-dark-lux {
      background-color: #000;
    }

    .text-gold {
      color: #d4af37;
    }

    .logo {
      max-height: 200px;
    }

    .section-title {
      color: #d4af37;
      font-size: 1.8rem;
      font-weight: bold;
      margin-bottom: 2rem;
    }

    .step-form {
      background: #1c1c1c;
      border-radius: 15px;
      padding: 2.5rem;
      box-shadow: 0 0 20px rgba(212, 175, 55, 0.2);
    }

    .form-label,
    .form-select {
      text-align: left;
    }

    .btn-gold {
      background-color: #d4af37;
      border: none;
      color: #000;
      font-weight: bold;
    }

    .btn-gold:hover {
      background-color: #c59e2f;
    }

    .product-card {
  background: #1e1e1e;
  border-radius: 12px;
  padding: 0.8rem;
  text-align: center;
  box-shadow: 0 0 10px rgba(212, 175, 55, 0.1);
  font-size: 0.95rem;
}

.product-card img {
  border-radius: 10px;
  border: 2px solid #d4af37;
  margin-bottom: 0.8rem;
  max-height: 280px;
  object-fit: cover;
}

.product-card h5 {
  font-size: 1rem;
}


    .testimonial {
      background: #1e1e1e;
      border-left: 4px solid #d4af37;
      padding: 1.2rem;
      border-radius: 10px;
      font-style: italic;
    }

    .carousel-marca img {
      max-height: 50px;
      object-fit: contain;
      filter: brightness(0) invert(1);
    }

    .marcas-carousel img {
      height: 60px;
      object-fit: contain;
      padding: 10px;
      filter: grayscale(100%);
      transition: transform 0.3s ease;
    }

    .marcas-carousel img:hover {
      filter: none;
      transform: scale(1.05);
    }

    footer a {
      color: #d4af37;
      text-decoration: none;
    }
    .brand-logo {
    max-height: 70px;
    object-fit: contain;
    filter: brightness(0) invert(1);
    transition: transform 0.3s ease;
  }
  .brand-logo:hover {
    transform: scale(1.1);
  }
  </style>
</head>

<body>
  <div class="text-center p-1 bg-dark-lux">
    <img src="assets/img/logomarca.png" alt="Lux Perfumes" class="logo" />
  </div>


  <section class="container py-5 text-center">
  <h2 class="text-warning mb-4">Assista antes de continuar</h2>
  <div class="d-flex justify-content-center">
    <div class="ratio ratio-16x9" style="max-width: 1000px; width: 100%;">
      <video autoplay muted loop playsinline controls class="rounded shadow w-100">
        <source src="/filtro/assets/1024.mp4" type="video/mp4">
        Seu navegador não suporta vídeo HTML5.
      </video>
    </div>
  </div>
</section>

  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-8 step-form">
        <h3 class="text-center section-title">Comece sua revenda agora</h3>
        <form id="leadForm">
          <div class="mb-3">
            <label class="form-label">Você quer revender ou comprar para uso próprio?</label>
            <select class="form-select" id="tipoCliente">
              <option>Revender</option>
              <option>Uso próprio</option>
            </select>
          </div>
          <div class="mb-3 d-none" id="campoCidade">
            <label class="form-label">Qual sua cidade?</label>
            <input type="text" class="form-control" id="cidade" placeholder="Digite sua cidade" />
          </div>
          <div class="mb-3 d-none" id="campoUnidades">
            <label class="form-label" id="labelUnidades">Quantas unidades pretende comprar?</label>
            <select class="form-select" id="quantidade">
                <option disabled selected>Selecione uma faixa</option>
                <option>2 a 5 unidades</option>
                <option>7 a 9 unidades</option>
                <option>Mais de 10 unidades</option>
            </select>
          </div>
          <button type="submit" class="btn btn-gold w-100 mt-3">Continuar</button>
        </form>
      </div>
    </div>
  </div>

  <div class="container py-5">
    <h4 class="text-center section-title">Destaques</h4>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="product-card">
          <img src="https://m.media-amazon.com/images/I/51+15oqQmLL.jpg" alt="Produto 1" class="img-fluid" />
          <h5 class="mt-2">Perfume Oud Luxury - R$ 89,90</h5>
        </div>
      </div>
      <div class="col-md-4">
        <div class="product-card">
          <img src="https://m.media-amazon.com/images/I/51+15oqQmLL.jpg" alt="Produto 2" class="img-fluid" />
          <h5 class="mt-2">Alhambra Classic - R$ 94,90</h5>
        </div>
      </div>
      <div class="col-md-4">
        <div class="product-card">
          <img src="https://m.media-amazon.com/images/I/51+15oqQmLL.jpg" alt="Produto 3" class="img-fluid" />
          <h5 class="mt-2">Afnan Supreme - R$ 99,90</h5>
        </div>
      </div>
    </div>
  </div>

  <section class="py-5 bg-black text-center text-white">
  <h2 class="text-warning mb-4">Marcas que trabalhamos</h2>
  <div class="d-flex justify-content-center flex-wrap gap-4">
    <img src="https://lattafa.com/wp-content/uploads/2024/04/lattafa-logo-final-2.svg" alt="Lattafa" class="brand-logo">
    <img src="https://lattafa.com/wp-content/uploads/2024/04/lattafa-logo-final-2.svg" alt="Afnan" class="brand-logo">
    <img src="https://lattafa.com/wp-content/uploads/2024/04/lattafa-logo-final-2.svg" alt="Al Haramain" class="brand-logo">
  </div>
</section>

  <div class="container py-5">
    <h4 class="text-center section-title">O que dizem sobre a Lux Perfumes</h4>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="testimonial">
          "Comecei comprando 3 perfumes e hoje tenho uma clientela fiel. Atendimento top!"
        </div>
      </div>
      <div class="col-md-4">
        <div class="testimonial">
          "Entrega rápida e produtos originais. Indico de olhos fechados."
        </div>
      </div>
      <div class="col-md-4">
        <div class="testimonial">
          "A Lux me ajudou a começar minha renda extra com confiança e qualidade."
        </div>
      </div>
    </div>
  </div>

  <footer class="text-center py-4 bg-dark-lux">
    Siga no Instagram <a href="https://instagram.com/luxperfumesbr">@luxperfumesbr</a><br />
    Enviamos para todo o Brasil via transportadora | Pedido mínimo de 2 unidades
  </footer>

  <script>
    const leadForm = document.getElementById('leadForm');
    const campoCidade = document.getElementById('campoCidade');
    const campoUnidades = document.getElementById('campoUnidades');
    const labelUnidades = document.getElementById('labelUnidades');
    const tipoCliente = document.getElementById('tipoCliente');

    let etapa = 0;

    tipoCliente.addEventListener('change', function () {
      if (tipoCliente.value === 'Revender') {
        labelUnidades.textContent = "Quantos perfumes você pretende iniciar suas vendas?";
      } else {
        labelUnidades.textContent = "Quantos perfumes você deseja comprar?";
      }
    });

    leadForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (etapa === 0) {
        campoCidade.classList.remove('d-none');
        etapa++;
      } else if (etapa === 1) {
        if (tipoCliente.value === 'Revender') {
          labelUnidades.textContent = "Quantos perfumes você pretende iniciar suas vendas?";
        } else {
          labelUnidades.textContent = "Quantos perfumes você deseja comprar?";
        }
        campoUnidades.classList.remove('d-none');
        etapa++;
      } else {
        const tipo = tipoCliente.value;
        const cidade = document.getElementById('cidade').value;
        const quantidade = document.getElementById('quantidade').value;

        window.location.href = `https://wa.me/SEUNUMERO?text=Olá! Quero ${tipo} com a Lux Perfumes.\nCidade: ${cidade}\nUnidades: ${quantidade}`;
      }
    });
  </script>

  <script>
    const marcasCarousel = document.querySelector('#marcasCarousel');
    const carousel = new bootstrap.Carousel(marcasCarousel, {
      interval: 4000,
      ride: 'carousel',
      pause: false,
      wrap: true
    });
  </script>
</body>

</html>
