import http from 'k6/http';
import { check } from 'k6';

export const options = {
  vus: 1,
  iterations: 1,
};

export default function () {
  const res = http.get('http://127.0.0.1:8088/contact.php');
  check(res, {
    'status is 200': (r) => r.status === 200,
    'has csrf_token': (r) => {
      const doc = r.html();
      const token = doc.find('input[name="csrf_token"]').attr('value');
      return token && token.length === 64;
    },
  });
}
